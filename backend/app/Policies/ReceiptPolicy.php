<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Receipt;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.receipts.read');
    }

    public function view(User $user, Receipt $r): bool
    {
        return $user->hasPermission('fms.receipts.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.receipts.write');
    }

    public function cancel(User $user, Receipt $r): bool
    {
        return $user->hasPermission('fms.receipts.write') && $r->isCancellable();
    }

    // Receipts are immutable once posted. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, Receipt $r): bool
    {
        return false;
    }

    public function delete(User $user, Receipt $r): bool
    {
        return false;
    }
}
