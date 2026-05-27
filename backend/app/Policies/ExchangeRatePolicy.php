<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExchangeRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.exchange_rate.read');
    }
    public function view(User $user, ExchangeRate $r): bool
    {
        return $user->hasPermission('fms.exchange_rate.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('fms.exchange_rate.write');
    }
    public function update(User $user, ExchangeRate $r): bool
    {
        return $user->hasPermission('fms.exchange_rate.write');
    }
    public function delete(User $user, ExchangeRate $r): bool
    {
        return $user->hasPermission('fms.exchange_rate.delete');
    }
}
