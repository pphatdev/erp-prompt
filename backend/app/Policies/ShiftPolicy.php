<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Shift;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Shift definitions are workforce-schedule metadata. Read is wide enough that
 * any employee with attendance self-service can resolve their assigned shift;
 * write is admin-only. Delete follows the dedicated `hrm.shift.delete` key
 * because removing a shift while live assignments exist needs explicit gating.
 */
class ShiftPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.shift.read')
            || $user->hasPermission('hrm.attendance.read')
            || $user->hasPermission('hrm.attendance.read.self')
            || $user->hasPermission('hrm.attendance.clock.self');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.shift.write');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->hasPermission('hrm.shift.write');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->hasPermission('hrm.shift.delete');
    }
}
