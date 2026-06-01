<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Account;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.accounts.read');
    }
    public function view(User $user, Account $a): bool
    {
        return $user->hasPermission('fms.accounts.read');
    }
    public function create(User $user): bool
    {
        return $user->hasPermission('fms.accounts.write');
    }
    public function update(User $user, Account $a): bool
    {
        return $user->hasPermission('fms.accounts.write');
    }
    public function delete(User $user, Account $a): bool
    {
        return $user->hasPermission('fms.accounts.delete');
    }
}
