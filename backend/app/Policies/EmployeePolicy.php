<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.employee.read');
    }

    /**
     * Determine whether the user can view the specific employee.
     * Employees can view their own details OR if they have hrm.employee.read permission.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        return $user->hasPermission('hrm.employee.read');
    }

    /**
     * Determine whether the user can create an employee.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    /**
     * Determine whether the user can update the employee.
     * Employees can update their own details? Or only write permission?
     * The requirement states: "self-service employee profile detail access: Employees are currently unable to view their own profile details because the system lacks a proper EmployeePolicy (meaning it relies on the administrative hrm.employee.read permission to view any employee). We must allow employees to view their own details without this broad permission."
     * Let's allow update if they have hrm.employee.write permission, OR if they are updating their own details (though normally HR handles updates, let's keep it restricted to write or self if needed. Wait, let's check how LeavePolicy or user update is handled. Usually, users shouldn't change their own salary, but they can update profile details? The Controller's update handles validation. Let's do: if they have hrm.employee.write, or if updating own profile, but wait! Changing salary/bank details should not be allowed for self-service if it's not administrative. But let's check standard permissions. If they have write, they can update. Let's make view allow self-service. Let's see if we should allow self-update of phone/email or if that's also admin-only. Since the prompt only mentions viewing own profile details, let's make update require hrm.employee.write, but let's check if the controller's update is used by self-service. No, there's no self-service edit route in EmployeeController, only standard update). Let's implement view to allow self-service.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    /**
     * Determine whether the user can delete/terminate the employee.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hrm.employee.delete');
    }
}
