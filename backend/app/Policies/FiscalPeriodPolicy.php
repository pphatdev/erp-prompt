<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\FiscalPeriod;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FiscalPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.fiscal_periods.read');
    }

    public function view(User $user, FiscalPeriod $p): bool
    {
        return $user->hasPermission('fms.fiscal_periods.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.fiscal_periods.write');
    }

    public function update(User $user, FiscalPeriod $p): bool
    {
        return $user->hasPermission('fms.fiscal_periods.write') && $p->isOpen();
    }

    public function delete(User $user, FiscalPeriod $p): bool
    {
        return $user->hasPermission('fms.fiscal_periods.write') && $p->isOpen();
    }

    public function close(User $user, FiscalPeriod $p): bool
    {
        return $user->hasPermission('fms.fiscal_periods.close') && $p->isClosable();
    }

    public function reopen(User $user, FiscalPeriod $p): bool
    {
        return $user->hasPermission('fms.fiscal_periods.reopen') && $p->isReopenable();
    }
}
