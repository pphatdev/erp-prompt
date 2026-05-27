<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.activities.read');
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $user->hasPermission('crm.activities.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.activities.write');
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->hasPermission('crm.activities.write');
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->hasPermission('crm.activities.delete');
    }
}
