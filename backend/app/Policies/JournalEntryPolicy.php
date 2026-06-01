<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JournalEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.ledger.read');
    }

    public function view(User $user, JournalEntry $j): bool
    {
        return $user->hasPermission('fms.ledger.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.ledger.write');
    }

    public function reverse(User $user, JournalEntry $j): bool
    {
        return $user->hasPermission('fms.ledger.write')
            && $j->status === 'posted'
            && $j->reversed_by_journal_id === null;
    }

    // Ledger postings are immutable. Corrections must be made via reverse() +
    // a fresh balanced entry — never by mutating or deleting history.
    public function update(User $user, JournalEntry $j): bool
    {
        return false;
    }

    public function delete(User $user, JournalEntry $j): bool
    {
        return false;
    }
}
