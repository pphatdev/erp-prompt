<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CreditNote;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CreditNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.credit_notes.read');
    }

    public function view(User $user, CreditNote $n): bool
    {
        return $user->hasPermission('fms.credit_notes.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.credit_notes.write');
    }

    public function cancel(User $user, CreditNote $n): bool
    {
        return $user->hasPermission('fms.credit_notes.write') && $n->isCancellable();
    }

    // Credit notes are immutable once issued. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, CreditNote $n): bool
    {
        return false;
    }

    public function delete(User $user, CreditNote $n): bool
    {
        return false;
    }
}
