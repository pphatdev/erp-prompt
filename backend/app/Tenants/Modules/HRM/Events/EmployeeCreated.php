<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Events;

use App\Models\Tenant\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an Employee row is committed - either via the manual
 * EmployeeService::createEmployee path or the
 * RecruitmentService::convertToEmployee flow from a hired candidate.
 *
 * Listeners use this to provision per-employee defaults (e.g.
 * ProvisionLeaveAllocations seeds employee_leave_allocations for the
 * current year). Dispatched via DB::afterCommit so a rolled-back
 * mint never leaves orphan downstream rows.
 */
class EmployeeCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Employee $employee) {}
}
