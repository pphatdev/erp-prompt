<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductVariantPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.product.read');
    }
    public function view(User $user, ProductVariant $v): bool
    {
        return $user->hasPermission('inventory.product.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.product.write');
    }
    public function update(User $user, ProductVariant $v): bool
    {
        return $user->hasPermission('inventory.product.write');
    }
    public function delete(User $user, ProductVariant $v): bool
    {
        return $user->hasPermission('inventory.product.delete');
    }
}
