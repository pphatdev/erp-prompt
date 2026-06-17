<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Listeners;

use App\Tenants\Modules\HRM\Events\EmployeeCreated;
use App\Tenants\Modules\HRM\Services\LeaveAllocationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seed employee_leave_allocations rows for the current year whenever
 * a new Employee is committed. The allocation rows are what
 * LeaveService::balanceFor reads, so without them new hires would
 * fall back to the legacy on-the-fly accrual math (Phase 11
 * behaviour) until an admin clicked Save in the allocations UI.
 *
 * Idempotent: LeaveAllocationService::provisionForEmployee uses
 * firstOrCreate, so a re-fire (e.g. after a manual reseed) never
 * duplicates rows.
 *
 * Listener swallows + logs exceptions so a failed downstream
 * provisioning doesn't roll back the Employee commit. The hire is
 * the user-facing action that must succeed; downstream allocation
 * problems are an admin-visible inconsistency we recover from with
 * a manual re-run, not a hire-abort.
 */
class ProvisionLeaveAllocations
{
    public function __construct(private readonly LeaveAllocationService $allocations) {}

    public function handle(EmployeeCreated $event): void
    {
        try {
            $this->allocations->provisionForEmployee($event->employee);
        } catch (Throwable $e) {
            Log::warning('Failed to auto-provision leave allocations on hire.', [
                'employee_id' => $event->employee->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
