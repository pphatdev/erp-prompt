<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\LeaveType;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.leave.read');
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $user->hasPermission('hrm.leave.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $user->hasPermission('hrm.leave.delete');
    }
}
