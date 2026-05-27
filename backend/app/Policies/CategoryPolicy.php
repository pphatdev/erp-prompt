<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Category;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.category.read');
    }
    public function view(User $user, Category $c): bool
    {
        return $user->hasPermission('inventory.category.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.category.write');
    }
    public function update(User $user, Category $c): bool
    {
        return $user->hasPermission('inventory.category.write');
    }
    public function delete(User $user, Category $c): bool
    {
        return $user->hasPermission('inventory.category.delete');
    }
}
