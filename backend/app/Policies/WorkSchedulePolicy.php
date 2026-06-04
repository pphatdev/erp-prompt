<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\User;
use App\Models\Tenant\WorkSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Work-schedule management is a settings-class action. Reading needs
 * `hrm.work_schedule.read` (or the broader `settings.read`); mutations
 * need `hrm.work_schedule.write` (or `settings.write`). Self-service
 * employees never get this grant — schedule data is admin-facing and
 * surfaces to staff only as pre-resolved values inside their own leave /
 * attendance payloads.
 */
class WorkSchedulePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.work_schedule.read')
            || $user->hasPermission('settings.read');
    }

    public function view(User $user, WorkSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.work_schedule.write')
            || $user->hasPermission('settings.write');
    }

    public function update(User $user, WorkSchedule $schedule): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, WorkSchedule $schedule): bool
    {
        return $this->create($user);
    }
}
