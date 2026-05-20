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

        $data['days']   = $data['days'] ?? ($start->diffInDays($end) + 1);
        $data['status'] = $this->statuses->initialFor('hrm.leave');

        return DB::transaction(function () use ($data) {
            $leave = Leave::create($data);

            // If a tenant has wired a workflow for module=hrm, type=leave, hand the
            // decision off to the eApprovals engine. Otherwise leave the legacy
            // direct approve/reject controllers as a stop-gap.
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

            return $leave;
        });
    }

    /**
     * Manual approve path used by the legacy /leaves/{leave}/approve endpoint.
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
                $remaining = $this->balanceFor($leave->employee_id, $leave->leave_type_id);

                if ($remaining < $leave->days) {
                    throw new DomainException("Insufficient leave balance ({$remaining} day(s) remaining).");
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

    public function balanceFor(string $employeeId, string $leaveTypeId): int
    {
        /** @var LeaveType|null $type */
        $type = LeaveType::find($leaveTypeId);
        if (!$type) {
            return 0;
        }

        $used = (int) Leave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('days');

        return max(0, (int) $type->annual_allowance - $used);
    }

    public function balanceSheetFor(Employee $employee): array
    {
        return LeaveType::query()->get()->map(function (LeaveType $type) use ($employee) {
            $used = (int) Leave::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');

            return [
                'leaveTypeId' => $type->id,
                'name' => $type->name,
                'annualAllowance' => (int) $type->annual_allowance,
                'used' => $used,
                'remaining' => max(0, (int) $type->annual_allowance - $used),
            ];
        })->all();
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
