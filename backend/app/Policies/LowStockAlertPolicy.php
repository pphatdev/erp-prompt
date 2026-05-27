<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\LowStockAlert;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;
class LowStockAlertPolicy
{
    use HandlesAuthorization;
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.alerts.view');
    }

    public function view(User $user, LowStockAlert $alert): bool
    {
        return $user->hasPermission('inventory.alerts.view');
    }

    public function acknowledge(User $user, LowStockAlert $alert): bool
    {
        return $user->hasPermission('inventory.alerts.manage');
    }

    public function resolve(User $user, LowStockAlert $alert): bool
    {
        return $user->hasPermission('inventory.alerts.manage');
    }
}
