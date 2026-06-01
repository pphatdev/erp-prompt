<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.task.read');
    }

    public function view(User $user, Task $t): bool
    {
        return $user->hasPermission('projects.task.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.task.write');
    }

    public function update(User $user, Task $t): bool
    {
        return $user->hasPermission('projects.task.write');
    }

    public function delete(User $user, Task $t): bool
    {
        return $user->hasPermission('projects.task.delete');
    }
}
