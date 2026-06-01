<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\LedgerEntry;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LedgerEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.ledger.read');
    }

    public function view(User $user, LedgerEntry $e): bool
    {
        return $user->hasPermission('fms.ledger.read');
    }

    // Ledger lines are only ever written via AccountingService::postEntry
    // inside a journal posting. Direct API mutations are never allowed.
    public function create(User $user, LedgerEntry $e): bool
    {
        return false;
    }

    public function update(User $user, LedgerEntry $e): bool
    {
        return false;
    }

    public function delete(User $user, LedgerEntry $e): bool
    {
        return false;
    }
}
