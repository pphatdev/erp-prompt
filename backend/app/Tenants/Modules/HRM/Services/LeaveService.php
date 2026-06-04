<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\ApprovalHistory;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Employee;
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
            // Caller-supplied `days` is honored as-is (UI may have pre-computed
            // partial days). Otherwise count only configured working-week days
            // between start and end so weekends don't burn balance.
            $data['days'] = isset($data['days'])
                ? (float) $data['days']
                : (float) $this->countWorkingDays($start, $end, $data['employee_id'] ?? null);
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
                // Re-add this leave's own days back into available since it's
                // currently counted in `locked` (status=pending). Otherwise
                // approving a leave that exactly equals remaining would always
                // 422.
                $remaining = $this->balanceFor($leave->employee_id, $leave->leave_type_id)
                    + (float) $leave->days;

                if ($remaining + 0.0001 < (float) $leave->days) {
                    throw new DomainException(sprintf(
                        'Insufficient leave balance (%.1f day(s) remaining).',
                        $remaining,
                    ));
                }
            }

            $leave->update(['status' => $finalStatus]);

            return $leave->fresh(['employee', 'leaveType']);
        });
    }

    /**
     * Withdraw a leave: soft-delete and short-circuit any active approval
     * request so approvers no longer see it in their queue.
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

            $leave->delete();
        });
    }

    /**
     * Count the working-day span between two dates inclusive, honoring
     * the hierarchical work_schedules resolver: Employee override ->
     * Department override -> Global default -> legacy
     * `hrm.leave.standard_work_week` fallback. Passing the employee id
     * (or null for a generic count) is enough — the resolver loads the
     * Employee row internally when needed.
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

    public function balanceFor(string $employeeId, string $leaveTypeId): float
    {
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

    public function balanceSheetFor(Employee $employee): array
    {
        return LeaveType::query()->get()->map(function (LeaveType $type) use ($employee) {
            $accrued = $this->accruedDaysFor($employee, $type);
            $used    = (float) Leave::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');

            $locked  = $this->lockedDaysFor($employee->id, $type->id);

            return [
                'leaveTypeId' => $type->id,
                'name' => $type->name,
                'annualAllowance' => (int) $type->annual_allowance,
                'accrued' => round($accrued, 2),
                'used' => round($used, 2),
                // remaining = accrued − (approved + pending). Pending is
                // captured by lockedDaysFor() to prevent double-spending
                // allowance via parallel requests.
                'remaining' => round(max(0.0, $accrued - $locked), 2),
            ];
        })->all();
    }

    /**
     * Spec §3.A.2 — Annual Allowance / 12 per month, accrued on the 1st.
     * Pro-rata for employees joining mid-year (count from `hired_at` month).
     *
     * Compute-on-the-fly: no per-employee accrual table yet. When carryover
     * policies land we can swap this for a stored ledger; signature stays.
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
     * Locked = approved + pending leaves for the current year. Pending counts
     * so that an approver can't push the employee past the cap by approving
     * stacked requests retroactively.
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
