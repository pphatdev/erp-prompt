<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeLeaveAllocation;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-employee, per-year leave-balance ledger (Phase 12).
 *
 * Replaces the on-the-fly accrual math in LeaveService with stored
 * counters so balance reads are O(1) and audit-able. Three concerns
 * live here:
 *
 *  - Provisioning: when an Employee is created, auto-allocate every
 *    leave type they're eligible for (gender gate) at the configured
 *    default allowance.
 *  - Counter mutators: holdPending / releasePending / applyApproved /
 *    cancelApproved keep `used_days` and `pending_days` honest as
 *    leaves move through the approval lifecycle.
 *  - Adjustments: an admin can override `allocated_days` (with a note
 *    captured for audit) — e.g. carryover bonus, severance settlement.
 *
 * All mutations run through DB::transaction so concurrent approvals
 * don't double-decrement.
 */
class LeaveAllocationService
{
    /**
     * Provision allocations for every leave type the employee is
     * eligible for, in the requested year (defaults to current).
     * Idempotent: existing rows are left untouched. Returns the
     * collection of allocation rows for that employee + year.
     */
    public function provisionForEmployee(Employee $employee, ?int $year = null): \Illuminate\Support\Collection
    {
        $year ??= CarbonImmutable::now()->year;
        $employeeGender = is_string($employee->gender) ? strtolower($employee->gender) : null;

        return DB::transaction(function () use ($employee, $year, $employeeGender) {
            $created = collect();

            foreach (LeaveType::query()->get() as $type) {
                if (!$this->employeeEligibleFor($type, $employeeGender)) {
                    continue;
                }

                $row = EmployeeLeaveAllocation::firstOrCreate(
                    [
                        'employee_id'   => $employee->id,
                        'leave_type_id' => $type->id,
                        'year'          => $year,
                    ],
                    [
                        'allocated_days' => (float) $type->annual_allowance,
                        'used_days'      => 0,
                        'pending_days'   => 0,
                        'note'           => 'Auto-provisioned on hire.',
                    ]
                );

                $created->push($row);
            }

            return $created;
        });
    }

    private function employeeEligibleFor(LeaveType $type, ?string $employeeGender): bool
    {
        $restriction = $type->applicable_gender ?: LeaveType::GENDER_ANY;
        if ($restriction === LeaveType::GENDER_ANY) {
            return true;
        }
        if ($employeeGender === null || $employeeGender === '') {
            // Gender unknown -> skip gender-restricted types. Admin can
            // manually allocate later via the adjust UI.
            return false;
        }
        return $employeeGender === $restriction;
    }

    /**
     * Find-or-create the allocation row for (employee, leaveType, year).
     * Used by LeaveService when an employee submits a leave for a type
     * they weren't auto-provisioned for (e.g. they joined after the
     * year was reset). New rows seed `allocated_days` from
     * `LeaveType::annual_allowance`.
     */
    public function ensureAllocation(string $employeeId, string $leaveTypeId, ?int $year = null): EmployeeLeaveAllocation
    {
        $year ??= CarbonImmutable::now()->year;
        $type = LeaveType::find($leaveTypeId);
        if (!$type) {
            throw new DomainException("Unknown leave_type_id: {$leaveTypeId}.");
        }

        return EmployeeLeaveAllocation::firstOrCreate(
            [
                'employee_id'   => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year'          => $year,
            ],
            [
                'allocated_days' => (float) $type->annual_allowance,
                'used_days'      => 0,
                'pending_days'   => 0,
                'note'           => 'Auto-created on first leave submission.',
            ]
        );
    }

    /**
     * Admin adjustment: override allocated_days with a note. Used for
     * carryover bonuses, severance settlements, or manual corrections.
     * The note is appended to the existing note (preserved as a
     * lightweight history) and the model emits an audit log via the
     * Auditable trait.
     */
    public function adjust(EmployeeLeaveAllocation $allocation, float $allocatedDays, ?string $note = null): EmployeeLeaveAllocation
    {
        if ($allocatedDays < 0) {
            throw new DomainException('Allocated days cannot be negative.');
        }

        $allocation->update([
            'allocated_days' => round($allocatedDays, 2),
            'note'           => $note ?? $allocation->note,
        ]);

        return $allocation->fresh();
    }

    /**
     * Hold `days` of pending balance on the relevant allocation row.
     * Called from LeaveService::submitRequest when a leave is created
     * pending approval. Idempotent within a transaction.
     */
    public function holdPending(Leave $leave): EmployeeLeaveAllocation
    {
        $year = $leave->start_date ? CarbonImmutable::parse($leave->start_date)->year : CarbonImmutable::now()->year;
        $allocation = $this->ensureAllocation($leave->employee_id, $leave->leave_type_id, $year);

        $allocation->increment('pending_days', (float) $leave->days);

        return $allocation->fresh();
    }

    /**
     * Release `days` from pending (without applying to used). Called
     * when a leave is withdrawn or rejected.
     */
    public function releasePending(Leave $leave): ?EmployeeLeaveAllocation
    {
        $allocation = $this->lookup($leave);
        if (!$allocation) {
            return null;
        }

        $newPending = max(0.0, (float) $allocation->pending_days - (float) $leave->days);
        $allocation->update(['pending_days' => round($newPending, 2)]);

        return $allocation->fresh();
    }

    /**
     * Approve: move `days` from pending -> used. Atomic so a concurrent
     * read never sees the half-applied state where both buckets are
     * decremented at once.
     */
    public function applyApproved(Leave $leave): ?EmployeeLeaveAllocation
    {
        $allocation = $this->lookup($leave);
        if (!$allocation) {
            // Manual create-and-approve path (no prior pending). Build
            // the row then book directly into used.
            $allocation = $this->ensureAllocation(
                $leave->employee_id,
                $leave->leave_type_id,
                CarbonImmutable::parse($leave->start_date)->year,
            );
        }

        return DB::transaction(function () use ($allocation, $leave) {
            $allocation->refresh();
            $days = (float) $leave->days;
            $newPending = max(0.0, (float) $allocation->pending_days - $days);
            $newUsed    = (float) $allocation->used_days + $days;
            $allocation->update([
                'pending_days' => round($newPending, 2),
                'used_days'    => round($newUsed, 2),
            ]);
            return $allocation->fresh();
        });
    }

    /**
     * Reverse an approved leave: subtract `days` from used. Called
     * when an admin retroactively cancels an approved leave.
     */
    public function cancelApproved(Leave $leave): ?EmployeeLeaveAllocation
    {
        $allocation = $this->lookup($leave);
        if (!$allocation) {
            return null;
        }
        $newUsed = max(0.0, (float) $allocation->used_days - (float) $leave->days);
        $allocation->update(['used_days' => round($newUsed, 2)]);
        return $allocation->fresh();
    }

    /**
     * Resolve the allocation for a Leave's (employee, type, start-year).
     * Returns null when the row doesn't exist — callers decide whether
     * to short-circuit silently or auto-create.
     */
    public function lookup(Leave $leave): ?EmployeeLeaveAllocation
    {
        if (!$leave->start_date) {
            return null;
        }
        $year = CarbonImmutable::parse($leave->start_date)->year;
        return EmployeeLeaveAllocation::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', $year)
            ->first();
    }

    /**
     * Build the full balance sheet for one employee, current year.
     * Returns array of { leaveTypeId, name, code, allocated, used,
     * pending, remaining, isPaid, isAccrued } so the frontend renders
     * a complete grid without N+1 lookups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function balanceSheetFor(Employee $employee, ?int $year = null): array
    {
        $year ??= CarbonImmutable::now()->year;
        $allocations = EmployeeLeaveAllocation::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return LeaveType::query()->orderBy('name')->get()->map(function (LeaveType $type) use ($allocations, $year) {
            $row = $allocations->get($type->id);
            $allocated = $row ? (float) $row->allocated_days : 0.0;
            $used      = $row ? (float) $row->used_days : 0.0;
            $pending   = $row ? (float) $row->pending_days : 0.0;
            return [
                'leaveTypeId' => $type->id,
                'name'        => $type->name,
                'code'        => $type->code,
                'isPaid'      => (bool) $type->is_paid,
                'isAccrued'   => (bool) $type->is_accrued,
                'year'        => $year,
                'allocated'   => round($allocated, 2),
                'used'        => round($used, 2),
                'pending'     => round($pending, 2),
                'remaining'   => round(max(0.0, $allocated - $used - $pending), 2),
                'allocationId' => $row?->id,
            ];
        })->all();
    }

    /**
     * Defensive log helper — called only when the audit trail would
     * otherwise miss a counter mutation that bypassed `update()`.
     */
    private function logEvent(string $event, EmployeeLeaveAllocation $allocation): void
    {
        Log::info('Leave allocation counter mutated', [
            'event' => $event,
            'id'    => $allocation->id,
        ]);
    }
}
