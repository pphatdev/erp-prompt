<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\ApprovalHistory;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeLeaveAllocation;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly ApprovalService $approvals,
        private readonly SettingService $settings,
        private readonly WorkScheduleService $workSchedules,
        private readonly LeaveAllocationService $allocations,
    ) {
    }

    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = Leave::query()->with(['employee', 'leaveType', 'approvalRequests']);

        if (!empty($filters['employeeId'])) {
            $query->where('employee_id', $filters['employeeId']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('start_date', 'desc');

        return $query;
    }

    public function submitRequest(array $data): Leave
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end   = CarbonImmutable::parse($data['end_date']);

        if ($end->lt($start)) {
            throw new DomainException('end_date must be on or after start_date.');
        }

        // Gender gating — `leave_types.applicable_gender` (any|male|female).
        // When the type restricts to a specific gender, the requesting
        // employee must match. `any` (default) bypasses the check entirely.
        $type = LeaveType::find($data['leave_type_id'] ?? null);
        if ($type) {
            $restriction = $type->applicable_gender ?: LeaveType::GENDER_ANY;
            if ($restriction !== LeaveType::GENDER_ANY) {
                $employee = Employee::find($data['employee_id'] ?? null);
                $employeeGender = $employee?->gender;
                if ($employeeGender === null || $employeeGender === '') {
                    throw new DomainException(sprintf(
                        '"%s" is restricted to %s employees, but the employee record has no gender on file.',
                        $type->name,
                        $restriction,
                    ));
                }
                if (strtolower((string) $employeeGender) !== $restriction) {
                    throw new DomainException(sprintf(
                        '"%s" is restricted to %s employees.',
                        $type->name,
                        $restriction,
                    ));
                }
            }
        }

        // hrm.leave.min_notice_days — submission must precede leave start by
        // at least N calendar days. 0 disables the check. Compared against
        // today (start-of-day) so a same-day submission counts as 0 notice.
        $minNotice = (int) ($this->settings->get('hrm.leave.min_notice_days') ?? 0);
        if ($minNotice > 0) {
            $today = CarbonImmutable::now()->startOfDay();
            $noticeDays = $today->diffInDays($start->startOfDay(), false);
            if ($noticeDays < $minNotice) {
                throw new DomainException(sprintf(
                    'Leave must be requested at least %d day(s) in advance.',
                    $minNotice,
                ));
            }
        }

        $session = $data['leave_session'] ?? Leave::SESSION_FULL_DAY;
        $data['leave_session'] = $session;

        if (in_array($session, Leave::HALF_DAY_SESSIONS, true)) {
            // Morning/afternoon imply a single calendar day with exactly 0.5d.
            if (!$start->isSameDay($end)) {
                throw new DomainException('Half-day leave must start and end on the same date.');
            }
            // Trust the session — overwrite any caller-supplied days so the
            // balance maths can't be spoofed by sending days=1 with session=morning.
            $data['days'] = 0.5;
        } else {
            // Caller-supplied `days` is ignored when missing OR zero so
            // a UI bug can't pass days=0 past balance validation. The
            // computed value sums per-day interval minutes via
            // WorkScheduleService (Phase 12) so a Saturday half-day
            // counts as 0.5 even on a full-day request.
            $computed = $this->countWorkingDaysDecimal($start, $end, $data['employee_id'] ?? null);
            $data['days'] = isset($data['days']) && (float) $data['days'] > 0
                ? (float) $data['days']
                : $computed;
        }

        // hrm.leave.max_consecutive_days — caps the duration of a single
        // request. 0 means unlimited. Half-day (0.5) never trips a positive cap.
        $maxConsecutive = (int) ($this->settings->get('hrm.leave.max_consecutive_days') ?? 0);
        if ($maxConsecutive > 0 && $data['days'] > $maxConsecutive) {
            throw new DomainException(sprintf(
                'Leave cannot exceed %d consecutive working day(s).',
                $maxConsecutive,
            ));
        }

        // hrm.leave.attachment_required_days — requests at or above this
        // threshold must include a supporting document. 0 disables. The
        // controller receives the upload and passes `attachment_path` here.
        $attachmentThreshold = (int) ($this->settings->get('hrm.leave.attachment_required_days') ?? 0);
        if ($attachmentThreshold > 0 && $data['days'] >= $attachmentThreshold) {
            $path = $data['attachment_path'] ?? null;
            if (!is_string($path) || trim($path) === '') {
                throw new DomainException(sprintf(
                    'A supporting document is required for leave of %d day(s) or more.',
                    $attachmentThreshold,
                ));
            }
        }

        // Balance pre-check INCLUDES pending so a user can't fan out N tiny
        // requests in parallel and approve them all past the cap later. The
        // hrm.leave.allow_negative_balance setting bypasses the throw — used
        // for emergency / unpaid leave flows.
        $allowNegative = (bool) ($this->settings->get('hrm.leave.allow_negative_balance') ?? false);
        $remaining = $this->balanceFor($data['employee_id'], $data['leave_type_id']);
        if (!$allowNegative && $remaining + 0.0001 < $data['days']) {
            throw new DomainException(sprintf(
                'Insufficient leave balance (%.1f day(s) remaining).',
                $remaining,
            ));
        }

        // hrm.leave.auto_approve_days — short requests skip the queue. We
        // decide BEFORE setting status so the initial status reflects the
        // shortcut and the eApprovals submit is skipped below.
        $autoApproveCap = (int) ($this->settings->get('hrm.leave.auto_approve_days') ?? 0);
        $autoApprove = $autoApproveCap > 0 && $data['days'] <= $autoApproveCap;

        $data['status'] = $autoApprove
            ? 'approved'
            : $this->statuses->initialFor('hrm.leave');

        return DB::transaction(function () use ($data, $autoApprove) {
            $leave = Leave::create($data);

            // Hold pending balance on the allocation row (auto-create
            // when the employee has no row for this type/year yet).
            // Auto-approved leaves skip the pending hop and book
            // straight into used.
            if ($autoApprove) {
                $this->allocations->applyApproved($leave);
            } else {
                $this->allocations->holdPending($leave);
            }

            // Skip workflow handoff when auto-approved — there's nothing to
            // route. Otherwise: if a tenant has wired a workflow for
            // module=hrm, type=leave, hand the decision off to the eApprovals
            // engine. Otherwise leave the legacy direct approve/reject
            // controllers as a stop-gap.
            if (!$autoApprove) {
                $workflow = $this->leaveWorkflow();
                $requesterId = Auth::id() ?? $leave->employee?->user_id;

                if ($workflow && $requesterId) {
                    $this->approvals->submitRequest(
                        workflowId: $workflow->id,
                        requesterId: (string) $requesterId,
                        requestableType: Leave::class,
                        requestableId: (string) $leave->id,
                    );
                }
            }

            return $leave;
        });
    }

    /**
     * Manual approve path used by the legacy /hrm/timeoff/leaves/{leave}/approve endpoint.
     * Blocked when an eApprovals request is active — clients must drive the
     * decision through /approval-requests/{id}/process instead.
     */
    public function approve(Leave $leave): Leave
    {
        if ($leave->activeApprovalRequest()) {
            throw new DomainException('This leave is in an eApprovals workflow. Use /api/v1/approval-requests/{id}/process.');
        }

        return $this->syncFromApproval($leave, 'approved');
    }

    /**
     * Manual reject path — same guard as approve().
     */
    public function reject(Leave $leave): Leave
    {
        if ($leave->activeApprovalRequest()) {
            throw new DomainException('This leave is in an eApprovals workflow. Use /api/v1/approval-requests/{id}/process.');
        }

        return $this->syncFromApproval($leave, 'rejected');
    }

    /**
     * Single place that flips a Leave to approved/rejected. Called from both
     * the legacy endpoints and the eApprovals listener so the same balance
     * guard and status validation apply regardless of path.
     */
    public function syncFromApproval(Leave $leave, string $finalStatus): Leave
    {
        $this->statuses->validateTransition('hrm.leave', $leave->status, $finalStatus);

        return DB::transaction(function () use ($leave, $finalStatus) {
            if ($finalStatus === 'approved') {
                // Balance pre-check excludes this leave's own pending hold
                // (it'll be released into `used` in the same transaction)
                // so a leave that exactly equals remaining doesn't 422.
                $remaining = $this->balanceFor($leave->employee_id, $leave->leave_type_id)
                    + (float) $leave->days;

                $allowNegative = (bool) ($this->settings->get('hrm.leave.allow_negative_balance') ?? false);
                if (!$allowNegative && $remaining + 0.0001 < (float) $leave->days) {
                    throw new DomainException(sprintf(
                        'Insufficient leave balance (%.1f day(s) remaining).',
                        $remaining,
                    ));
                }
            }

            $leave->update(['status' => $finalStatus]);

            // Update the allocation counters. Approved -> pending becomes
            // used. Rejected -> pending released without consuming balance.
            if ($finalStatus === 'approved') {
                $this->allocations->applyApproved($leave);
            } elseif ($finalStatus === 'rejected') {
                $this->allocations->releasePending($leave);
            }

            return $leave->fresh(['employee', 'leaveType']);
        });
    }

    /**
     * Withdraw a leave: soft-delete and short-circuit any active approval
     * request so approvers no longer see it in their queue. Releases any
     * held pending balance back to the allocation row.
     */
    public function withdraw(Leave $leave): void
    {
        DB::transaction(function () use ($leave) {
            $active = $leave->activeApprovalRequest();

            if ($active) {
                ApprovalHistory::create([
                    'approval_request_id' => $active->id,
                    'approver_id'         => Auth::id() ?? $leave->employee?->user_id,
                    'action'              => 'cancelled',
                    'comment'             => 'Withdrawn by requester.',
                ]);

                $active->update([
                    'status'           => 'cancelled',
                    'current_level_id' => null,
                ]);
            }

            // Return the held balance. Withdrawing a previously-approved
            // leave subtracts from `used`; pending leaves only release
            // from `pending`.
            if ($leave->status === 'approved') {
                $this->allocations->cancelApproved($leave);
            } else {
                $this->allocations->releasePending($leave);
            }

            $leave->delete();
        });
    }

    /**
     * Integer working-day count between two dates inclusive (legacy
     * Phase 11 signature). Skips non-work days but does not weight by
     * interval minutes. Kept stable for callers that need a whole-day
     * count (e.g. `max_consecutive_days` check, reporting).
     */
    public function countWorkingDays(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId = null): int
    {
        $employee = $employeeId ? Employee::find($employeeId) : null;
        return $this->workSchedules->countWorkingDays(
            $start->startOfDay(),
            $end->startOfDay(),
            $employee,
        );
    }

    /**
     * Decimal working-day count honoring per-day interval minutes
     * (Phase 12). A Saturday with a 4-hour schedule contributes 0.5
     * at the default 8-hour standardDailyHours. Used by
     * `submitRequest` so half-day work schedules surface as fractional
     * leave durations.
     */
    public function countWorkingDaysDecimal(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId = null): float
    {
        $employee = $employeeId ? Employee::find($employeeId) : null;
        $standardDailyHours = (float) ($this->settings->get('hrm.leave.standard_daily_hours') ?? 8.0);
        if ($standardDailyHours <= 0.0) {
            $standardDailyHours = 8.0;
        }
        return $this->workSchedules->countWorkingDaysDecimal(
            $start->startOfDay(),
            $end->startOfDay(),
            $employee,
            $standardDailyHours,
        );
    }

    /**
     * Remaining balance for one (employee, leaveType) pair. Reads the
     * stored allocation row (Phase 12) — `allocated - used - pending`,
     * floored at 0. When no allocation row exists (e.g. employee
     * predates Phase 12), falls back to the legacy on-the-fly accrual
     * math so legacy tenants keep working until they backfill.
     */
    public function balanceFor(string $employeeId, string $leaveTypeId): float
    {
        $year = CarbonImmutable::now()->year;
        $allocation = EmployeeLeaveAllocation::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($allocation) {
            $remaining = $allocation->remainingDays();

            // Pro-rata cap for accrued leave types: even when the
            // allocation row reflects the full annual entitlement, an
            // employee in March can't book December's portion early.
            // Computed as `accruedToDate - (used + pending)`.
            $type = LeaveType::find($leaveTypeId);
            if ($type && $type->is_accrued) {
                $employee = Employee::find($employeeId);
                if ($employee) {
                    $accruedToDate = $this->accruedDaysFor($employee, $type);
                    $held = (float) $allocation->used_days + (float) $allocation->pending_days;
                    $remainingAccrued = max(0.0, $accruedToDate - $held);
                    $remaining = min($remaining, $remainingAccrued);
                }
            }
            return $remaining;
        }

        // ---- legacy back-compat fallback (Phase 11 behaviour) ----
        /** @var LeaveType|null $type */
        $type = LeaveType::find($leaveTypeId);
        if (!$type) {
            return 0.0;
        }

        $employee = Employee::find($employeeId);
        $accrued  = $employee ? $this->accruedDaysFor($employee, $type) : (float) $type->annual_allowance;
        $locked   = $this->lockedDaysFor($employeeId, $leaveTypeId);

        return max(0.0, $accrued - $locked);
    }

    /**
     * Per-employee balance sheet for the current year. Pulls every
     * leave_type, looks up its allocation row, returns
     * { leaveTypeId, name, code, allocated, used, pending, remaining,
     *   isPaid, isAccrued, annualAllowance }. Types without an
     * allocation row fall back to a synthetic accrued/used/locked
     * triple so admin UIs render rows for legacy tenants too.
     */
    public function balanceSheetFor(Employee $employee): array
    {
        $year = CarbonImmutable::now()->year;
        $allocations = EmployeeLeaveAllocation::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return LeaveType::query()->orderBy('name')->get()->map(function (LeaveType $type) use ($employee, $allocations) {
            $row = $allocations->get($type->id);
            if ($row) {
                return [
                    'leaveTypeId'     => $type->id,
                    'name'            => $type->name,
                    'code'            => $type->code,
                    'isPaid'          => (bool) $type->is_paid,
                    'isAccrued'       => (bool) $type->is_accrued,
                    'annualAllowance' => (int) $type->annual_allowance,
                    'allocated'       => round((float) $row->allocated_days, 2),
                    'used'            => round((float) $row->used_days, 2),
                    'pending'         => round((float) $row->pending_days, 2),
                    'remaining'       => $row->remainingDays(),
                    'allocationId'    => $row->id,
                ];
            }

            // Legacy fallback for tenants without allocation rows yet.
            $accrued = $this->accruedDaysFor($employee, $type);
            $locked  = $this->lockedDaysFor($employee->id, $type->id);
            $used    = (float) Leave::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');

            return [
                'leaveTypeId'     => $type->id,
                'name'            => $type->name,
                'code'            => $type->code,
                'isPaid'          => (bool) $type->is_paid,
                'isAccrued'       => (bool) $type->is_accrued,
                'annualAllowance' => (int) $type->annual_allowance,
                'allocated'       => round($accrued, 2),
                'used'            => round($used, 2),
                'pending'         => round(max(0.0, $locked - $used), 2),
                'remaining'       => round(max(0.0, $accrued - $locked), 2),
                'allocationId'    => null,
            ];
        })->all();
    }

    /**
     * Spec §3.A.2 — Annual Allowance / 12 per month, accrued on the 1st.
     * Pro-rata for employees joining mid-year (count from `hired_at` month).
     * Still used as a back-compat fallback when no allocation row exists.
     */
    public function accruedDaysFor(Employee $employee, LeaveType $type): float
    {
        $annual = (float) $type->annual_allowance;
        if ($annual <= 0) {
            return 0.0;
        }

        $now    = CarbonImmutable::now();
        $hired  = $employee->hired_at
            ? CarbonImmutable::parse($employee->hired_at)
            : $now->startOfYear();

        // The accrual year starts in January for employees hired before this
        // year, or in their hire month for employees hired mid-year.
        $start = $hired->year < $now->year
            ? $now->startOfYear()
            : $hired->startOfMonth();

        if ($start->greaterThan($now)) {
            return 0.0;   // Not started yet.
        }

        // Inclusive month count: hired in Jan, current Mar → 3 months (Jan/Feb/Mar).
        $months = ($now->year - $start->year) * 12 + ($now->month - $start->month) + 1;
        $months = max(0, min(12, $months));

        $monthly = round($annual / 12, 2);

        return min($annual, $monthly * $months);
    }

    /**
     * Legacy back-compat: locked = approved + pending leaves for the
     * current year. Used only as fallback when no allocation row
     * exists (Phase 12 reads counters directly from the allocation).
     */
    private function lockedDaysFor(string $employeeId, string $leaveTypeId): float
    {
        return (float) Leave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereYear('start_date', now()->year)
            ->sum('days');
    }

    private function leaveWorkflow(): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('module', 'hrm')
            ->where('type', 'leave')
            ->orderBy('created_at')
            ->first();
    }
}
