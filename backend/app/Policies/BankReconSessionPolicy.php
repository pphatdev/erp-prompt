<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\BankReconSession;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BankReconSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.bank_recon.read');
    }

    public function view(User $user, BankReconSession $s): bool
    {
        return $user->hasPermission('fms.bank_recon.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.bank_recon.write');
    }

    /** Add statement lines, match/unmatch — allowed only while session is open. */
    public function modify(User $user, BankReconSession $s): bool
    {
        return $user->hasPermission('fms.bank_recon.write') && $s->isOpen();
    }

    public function close(User $user, BankReconSession $s): bool
    {
        return $user->hasPermission('fms.bank_recon.write') && $s->isClosable();
    }

    /** Reopen is gated separately — a closed session is immutable by default. */
    public function reopen(User $user, BankReconSession $s): bool
    {
        return $user->hasPermission('fms.bank_recon.reopen') && $s->isClosed();
    }

    // Sessions are not directly editable — modifications flow through the
    // service actions (addStatementLine / match / close / ...). Update/delete
    // are explicitly off the table to preserve audit history.
    public function update(User $user, BankReconSession $s): bool
    {
        return false;
    }

    public function delete(User $user, BankReconSession $s): bool
    {
        return false;
    }
}
