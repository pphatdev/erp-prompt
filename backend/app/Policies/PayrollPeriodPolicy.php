<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.payroll.read');
    }

    public function view(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('hrm.payroll.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.payroll.write');
    }

    public function update(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('hrm.payroll.write');
    }

    public function delete(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('hrm.payroll.write');
    }

    /**
     * Processing a period generates payslips for every active employee.
     * Same write-gate as period creation — closing/finalising is a
     * payroll-officer action, not an HR one.
     */
    public function process(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('hrm.payroll.write');
    }

    public function close(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('hrm.payroll.write');
    }
}
