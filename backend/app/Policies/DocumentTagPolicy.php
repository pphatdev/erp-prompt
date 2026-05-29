<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Tag;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentTagPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('edocs.tags.read');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasPermission('edocs.tags.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('edocs.tags.write');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasPermission('edocs.tags.write');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasPermission('edocs.tags.delete');
    }
}
