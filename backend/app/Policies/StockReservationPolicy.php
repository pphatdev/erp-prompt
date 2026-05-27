<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\StockReservation;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Reservations split read/write from the privileged `commit` action so
 * a POS terminal role can hold/release inventory without being able to
 * post the final stock-out movement itself (that's the cashier/manager).
 */
class StockReservationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.reservations.read');
    }
    public function view(User $user, StockReservation $r): bool
    {
        return $user->hasPermission('inventory.reservations.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.reservations.write');
    }
    public function update(User $user, StockReservation $r): bool
    {
        return $user->hasPermission('inventory.reservations.write');
    }
    public function commit(User $user, StockReservation $r): bool
    {
        return $user->hasPermission('inventory.reservations.commit');
    }
}
