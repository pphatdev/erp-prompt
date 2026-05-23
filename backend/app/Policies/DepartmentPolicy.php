<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Departments and Positions are workforce-structure metadata, so they share
 * the `hrm.employee.*` permissions rather than carrying their own permission
 * keys. Read is wide (any employee with self-service can resolve their own
 * department); write is admin-only.
 */
class DepartmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.employee.read')
            || $user->hasPermission('hrm.employee.read.self');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermission('hrm.employee.read')
            || $user->hasPermission('hrm.employee.read.self');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermission('hrm.employee.delete');
    }
}
