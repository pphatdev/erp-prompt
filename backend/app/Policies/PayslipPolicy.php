<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Payslip;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayslipPolicy
{
    use HandlesAuthorization;

    /**
     * Listing is gated by the admin permission. Self-service callers go
     * through the same endpoint with `hrm.payslip.read.self` — the
     * controller is responsible for force-filtering the query to their
     * own employee_id so they can't enumerate other employees' rows.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.payroll.read')
            || $user->hasPermission('hrm.payslip.read.self');
    }

    public function view(User $user, Payslip $payslip): bool
    {
        if ($this->ownsPayslip($user, $payslip) && $user->hasPermission('hrm.payslip.read.self')) {
            return true;
        }

        return $user->hasPermission('hrm.payroll.read');
    }

    private function ownsPayslip(User $user, Payslip $payslip): bool
    {
        $employeeId = $user->employee?->id;
        return $employeeId !== null && $employeeId === $payslip->employee_id;
    }
}
