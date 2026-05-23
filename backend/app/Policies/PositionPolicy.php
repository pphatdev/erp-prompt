<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Position;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Shares the `hrm.employee.*` permission set with DepartmentPolicy —
 * positions are workforce-structure metadata, not a standalone feature.
 */
class PositionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.employee.read')
            || $user->hasPermission('hrm.employee.read.self');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasPermission('hrm.employee.read')
            || $user->hasPermission('hrm.employee.read.self');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermission('hrm.employee.delete');
    }
}
