<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;
class StockTransferPolicy
{
    use HandlesAuthorization;
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.transfer.read');
    }

    public function view(User $user, StockTransfer $t): bool
    {
        return $user->hasPermission('inventory.transfer.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.transfer.write');
    }

    public function dispatch(User $user, StockTransfer $t): bool
    {
        return $user->hasPermission('inventory.transfer.write');
    }

    public function receive(User $user, StockTransfer $t): bool
    {
        return $user->hasPermission('inventory.transfer.write');
    }

    public function cancel(User $user, StockTransfer $t): bool
    {
        return $user->hasPermission('inventory.transfer.write');
    }
}
