<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\EmployeeLeaveAllocation;
use App\Models\Tenant\User;

class EmployeeLeaveAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.leave_allocation.read')
            || $user->hasPermission('hrm.leave.read');
    }

    public function view(User $user, EmployeeLeaveAllocation $allocation): bool
    {
        // Self-service: an employee can read their own row even
        // without the admin grant. Mirrors `hrm.leave.read.self`.
        if ($user->employee && $user->employee->id === $allocation->employee_id) {
            return true;
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.leave_allocation.write');
    }

    public function update(User $user, EmployeeLeaveAllocation $allocation): bool
    {
        return $user->hasPermission('hrm.leave_allocation.write');
    }

    public function delete(User $user, EmployeeLeaveAllocation $allocation): bool
    {
        return $user->hasPermission('hrm.leave_allocation.delete');
    }
}
