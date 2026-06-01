<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Bill;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.bills.read');
    }

    public function view(User $user, Bill $b): bool
    {
        return $user->hasPermission('fms.bills.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.bills.write');
    }

    public function update(User $user, Bill $b): bool
    {
        // Only draft bills are editable. Approved bills are immutable; correct
        // via cancel (posts a reversal JE) or via the upcoming Debit Note flow.
        return $user->hasPermission('fms.bills.write') && $b->isEditable();
    }

    public function approve(User $user, Bill $b): bool
    {
        return $user->hasPermission('fms.bills.write') && $b->isPostable();
    }

    public function cancel(User $user, Bill $b): bool
    {
        return $user->hasPermission('fms.bills.write') && $b->status !== Bill::STATUS_CANCELLED;
    }

    public function delete(User $user, Bill $b): bool
    {
        // Only draft bills may be hard-archived. Anything posted to the GL
        // stays for audit; corrections flow through cancel().
        return $user->hasPermission('fms.bills.delete') && $b->isEditable();
    }
}
