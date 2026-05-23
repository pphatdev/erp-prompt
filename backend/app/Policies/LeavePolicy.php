<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Leave;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeavePolicy
{
    use HandlesAuthorization;

    /**
     * Both admin (`hrm.leave.read`) and self-service (`hrm.leave.read.self`)
     * may hit the index endpoint. LeaveController is responsible for
     * force-filtering to the caller's own employee_id when the caller
     * only holds the `.self` permission — so listing never leaks rows
     * belonging to other employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.leave.read')
            || $user->hasPermission('hrm.leave.read.self');
    }

    public function view(User $user, Leave $leave): bool
    {
        if ($this->ownsLeave($user, $leave) && $user->hasPermission('hrm.leave.read.self')) {
            return true;
        }

        return $user->hasPermission('hrm.leave.read');
    }

    /**
     * Self-service: an employee with `hrm.leave.write.self` may submit
     * their own request. Privileged callers (HR with `hrm.leave.write`)
     * may submit on behalf of another employee. Service-layer validation
     * still asserts employee_id matches the caller for self-service
     * submissions.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.leave.write')
            || $user->hasPermission('hrm.leave.write.self');
    }

    public function update(User $user, Leave $leave): bool
    {
        return $user->hasPermission('hrm.leave.write');
    }

    public function delete(User $user, Leave $leave): bool
    {
        if ($this->ownsLeave($user, $leave)
            && $leave->status === 'pending'
            && $user->hasPermission('hrm.leave.write.self')
        ) {
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
