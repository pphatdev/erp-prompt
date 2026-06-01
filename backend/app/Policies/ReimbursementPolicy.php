<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Reimbursement;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReimbursementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.reimbursements.read');
    }

    public function view(User $user, Reimbursement $r): bool
    {
        return $user->hasPermission('fms.reimbursements.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.reimbursements.write');
    }

    public function cancel(User $user, Reimbursement $r): bool
    {
        return $user->hasPermission('fms.reimbursements.write') && $r->isCancellable();
    }

    // Reimbursements are immutable once recorded. Corrections flow through
    // cancel() which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, Reimbursement $r): bool
    {
        return false;
    }

    public function delete(User $user, Reimbursement $r): bool
    {
        return false;
    }
}
