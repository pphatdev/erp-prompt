<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.procurement.read');
    }
    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermission('inventory.procurement.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.procurement.write');
    }
    public function update(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermission('inventory.procurement.write');
    }
    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermission('inventory.procurement.delete');
    }

    /** Separate gate for approve — finance/manager separation of duties. */
    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermission('inventory.procurement.approve');
    }
}
