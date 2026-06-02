<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcomCustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool           { return $user->hasPermission('ecommerce.customers.read'); }
    public function view(User $user, EcomCustomer $c): bool { return $user->hasPermission('ecommerce.customers.read'); }
    public function update(User $user, EcomCustomer $c): bool { return $user->hasPermission('ecommerce.customers.write'); }
}
