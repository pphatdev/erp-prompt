<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CalendarEvent;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Calendar event access:
 *   read/write/delete   - admin / manager scope (`calendar.event.*`)
 *   .self variants      - employee can list their own events and create
 *                         personal entries scoped to their employee_id.
 *                         Update/delete of a self-event requires either
 *                         `calendar.event.write` or being the event's
 *                         employee_id.
 */
class CalendarEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('calendar.event.read')
            || $user->hasPermission('calendar.event.read.self');
    }

    public function view(User $user, CalendarEvent $e): bool
    {
        if ($user->hasPermission('calendar.event.read')) {
            return true;
        }
        return $user->hasPermission('calendar.event.read.self')
            && $this->isOwn($user, $e);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('calendar.event.write')
            || $user->hasPermission('calendar.event.write.self');
    }

    public function update(User $user, CalendarEvent $e): bool
    {
        if ($user->hasPermission('calendar.event.write')) {
            return true;
        }
        return $user->hasPermission('calendar.event.write.self')
            && $this->isOwn($user, $e);
    }

    public function delete(User $user, CalendarEvent $e): bool
    {
        if ($user->hasPermission('calendar.event.delete')) {
            return true;
        }
        return $user->hasPermission('calendar.event.write.self')
            && $this->isOwn($user, $e);
    }

    private function isOwn(User $user, CalendarEvent $e): bool
    {
        $employeeId = $user->employee?->id;
        return $employeeId !== null && $employeeId === $e->employee_id;
    }
}
