<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\OvertimeRequest;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OvertimeRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.overtime.read')
            || $user->hasPermission('hrm.overtime.read.self');
    }

    public function view(User $user, OvertimeRequest $request): bool
    {
        if ($user->hasPermission('hrm.overtime.read')) {
            return true;
        }
        if ($user->hasPermission('hrm.overtime.read.self')) {
            return $user->employee?->id === $request->employee_id;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.overtime.write')
            || $user->hasPermission('hrm.overtime.write.self');
    }

    /**
     * Approve / reject — admin-only. Self-service `write.self` users get
     * submit + cancel via the dedicated `cancel` ability below.
     */
    public function process(User $user, OvertimeRequest $request): bool
    {
        return $user->hasPermission('hrm.overtime.write');
    }

    public function cancel(User $user, OvertimeRequest $request): bool
    {
        if ($user->hasPermission('hrm.overtime.write')) {
            return true;
        }
        // Owner can cancel only while still pending.
        return $user->hasPermission('hrm.overtime.write.self')
            && $user->employee?->id === $request->employee_id
            && $request->status === OvertimeRequest::STATUS_PENDING;
    }

    public function delete(User $user, OvertimeRequest $request): bool
    {
        return $user->hasPermission('hrm.overtime.delete');
    }
}
