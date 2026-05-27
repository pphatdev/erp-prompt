<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Opportunity;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OpportunityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.opportunities.read');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('crm.opportunities.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.opportunities.write');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('crm.opportunities.write');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('crm.opportunities.delete');
    }
}
