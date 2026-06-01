<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\DebitNote;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DebitNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.debit_notes.read');
    }

    public function view(User $user, DebitNote $n): bool
    {
        return $user->hasPermission('fms.debit_notes.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.debit_notes.write');
    }

    public function cancel(User $user, DebitNote $n): bool
    {
        return $user->hasPermission('fms.debit_notes.write') && $n->isCancellable();
    }

    // Debit notes are immutable once issued. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, DebitNote $n): bool
    {
        return false;
    }

    public function delete(User $user, DebitNote $n): bool
    {
        return false;
    }
}
