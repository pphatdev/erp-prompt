<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\PosOrder;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool       { return $user->hasPermission('pos.order.read'); }
    public function view(User $user, PosOrder $o): bool
    {
        return $user->hasPermission('pos.order.read') || $o->cashier_id === $user->id;
    }
    public function create(User $user): bool        { return $user->hasPermission('pos.order.write'); }
    public function void(User $user, PosOrder $o): bool { return $user->hasPermission('pos.order.void'); }
}
