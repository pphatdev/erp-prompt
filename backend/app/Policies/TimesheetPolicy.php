<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Timesheet;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.timesheet.read');
    }

    public function view(User $user, Timesheet $t): bool
    {
        return $user->hasPermission('projects.timesheet.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.timesheet.write');
    }

    public function update(User $user, Timesheet $t): bool
    {
        return $user->hasPermission('projects.timesheet.write');
    }

    public function delete(User $user, Timesheet $t): bool
    {
        return $user->hasPermission('projects.timesheet.delete');
    }
}
