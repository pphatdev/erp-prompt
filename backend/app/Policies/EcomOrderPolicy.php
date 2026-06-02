<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcomOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool        { return $user->hasPermission('ecommerce.orders.read'); }
    public function view(User $user, EcomOrder $o): bool { return $user->hasPermission('ecommerce.orders.read'); }
    public function update(User $user, EcomOrder $o): bool { return $user->hasPermission('ecommerce.orders.write'); }
    public function cancel(User $user, EcomOrder $o): bool { return $user->hasPermission('ecommerce.orders.cancel'); }
}
