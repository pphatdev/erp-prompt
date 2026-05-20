<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Leave;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeavePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.leave.read');
    }

    public function view(User $user, Leave $leave): bool
    {
        if ($this->ownsLeave($user, $leave)) {
            return true;
        }
        return $user->hasPermission('hrm.leave.read');
    }

    /**
     * Self-service: any authenticated employee may submit their own request.
     * Privileged callers (HR) may submit on behalf of another employee.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Leave $leave): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    public function delete(User $user, Leave $leave): bool
    {
        if ($this->ownsLeave($user, $leave) && $leave->status === 'pending') {
            return true;
        }
        return $user->hasPermission('hrm.leave.delete');
    }

    public function approve(User $user, Leave $leave): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    public function reject(User $user, Leave $leave): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    private function ownsLeave(User $user, Leave $leave): bool
    {
        $employeeId = $user->employee?->id;
        return $employeeId !== null && $employeeId === $leave->employee_id;
    }
}
