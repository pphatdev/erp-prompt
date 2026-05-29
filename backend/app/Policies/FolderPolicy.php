<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Folder;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('edocs.explorer.read');
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->hasPermission('edocs.explorer.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('edocs.explorer.write');
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->hasPermission('edocs.explorer.write');
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->hasPermission('edocs.explorer.delete');
    }
}
