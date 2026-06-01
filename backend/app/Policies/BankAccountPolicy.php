<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\BankAccount;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BankAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.bank_accounts.read');
    }

    public function view(User $user, BankAccount $b): bool
    {
        return $user->hasPermission('fms.bank_accounts.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.bank_accounts.write');
    }

    public function update(User $user, BankAccount $b): bool
    {
        return $user->hasPermission('fms.bank_accounts.write');
    }

    public function delete(User $user, BankAccount $b): bool
    {
        return $user->hasPermission('fms.bank_accounts.delete');
    }
}
