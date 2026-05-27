<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CrmContact;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmContactPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.contacts.read');
    }

    public function view(User $user, CrmContact $contact): bool
    {
        return $user->hasPermission('crm.contacts.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.contacts.write');
    }

    public function update(User $user, CrmContact $contact): bool
    {
        return $user->hasPermission('crm.contacts.write');
    }

    public function delete(User $user, CrmContact $contact): bool
    {
        return $user->hasPermission('crm.contacts.delete');
    }
}
