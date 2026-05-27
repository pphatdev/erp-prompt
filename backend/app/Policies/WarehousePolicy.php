<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarehousePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool   { return $user->hasPermission('inventory.warehouse.read'); }
    public function view(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.warehouse.read'); }
    public function create(User $user): bool    { return $user->hasPermission('inventory.warehouse.write'); }
    public function update(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.warehouse.write'); }
    public function delete(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.warehouse.delete'); }
}
