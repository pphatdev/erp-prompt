<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return $user->hasPermission('inventory.suppliers.read'); }
    public function view(User $user, Supplier $s): bool { return $user->hasPermission('inventory.suppliers.read'); }
    public function create(User $user): bool  { return $user->hasPermission('inventory.suppliers.write'); }
    public function update(User $user, Supplier $s): bool { return $user->hasPermission('inventory.suppliers.write'); }
    public function delete(User $user, Supplier $s): bool { return $user->hasPermission('inventory.suppliers.delete'); }
}
