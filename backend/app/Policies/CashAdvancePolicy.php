<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CashAdvance;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashAdvancePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.cash_advances.read');
    }

    public function view(User $user, CashAdvance $a): bool
    {
        return $user->hasPermission('fms.cash_advances.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.cash_advances.write');
    }

    public function cancel(User $user, CashAdvance $a): bool
    {
        return $user->hasPermission('fms.cash_advances.write') && $a->isCancellable();
    }

    // Immutable once issued. Corrections flow through cancel() (when no
    // settlements have applied) or by reversing the settlements first.
    public function update(User $user, CashAdvance $a): bool
    {
        return false;
    }

    public function delete(User $user, CashAdvance $a): bool
    {
        return false;
    }
}
