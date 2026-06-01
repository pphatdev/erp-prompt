<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.project.read');
    }

    public function view(User $user, Project $p): bool
    {
        return $user->hasPermission('projects.project.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.project.write');
    }

    public function update(User $user, Project $p): bool
    {
        return $user->hasPermission('projects.project.write');
    }

    public function delete(User $user, Project $p): bool
    {
        return $user->hasPermission('projects.project.delete');
    }
}
