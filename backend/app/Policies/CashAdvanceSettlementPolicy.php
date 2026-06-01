<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CashAdvanceSettlement;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashAdvanceSettlementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.cash_advances.read');
    }

    public function view(User $user, CashAdvanceSettlement $s): bool
    {
        return $user->hasPermission('fms.cash_advances.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.cash_advances.settle');
    }

    public function cancel(User $user, CashAdvanceSettlement $s): bool
    {
        return $user->hasPermission('fms.cash_advances.settle') && $s->isCancellable();
    }

    // Settlements are immutable once posted. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, CashAdvanceSettlement $s): bool
    {
        return false;
    }

    public function delete(User $user, CashAdvanceSettlement $s): bool
    {
        return false;
    }
}
