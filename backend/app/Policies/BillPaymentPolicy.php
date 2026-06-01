<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\BillPayment;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillPaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.bill_payments.read');
    }

    public function view(User $user, BillPayment $p): bool
    {
        return $user->hasPermission('fms.bill_payments.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.bill_payments.write');
    }

    public function cancel(User $user, BillPayment $p): bool
    {
        return $user->hasPermission('fms.bill_payments.write') && $p->isCancellable();
    }

    // Payments are immutable once recorded. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or hard delete.
    public function update(User $user, BillPayment $p): bool
    {
        return false;
    }

    public function delete(User $user, BillPayment $p): bool
    {
        return false;
    }
}
