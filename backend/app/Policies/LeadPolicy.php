<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Lead;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.leads.read');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermission('crm.leads.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.leads.write');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermission('crm.leads.write');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('crm.leads.delete');
    }

    public function qualify(User $user, Lead $lead): bool
    {
        return $user->hasPermission('crm.leads.write')
            && $user->hasPermission('crm.opportunities.write');
    }
}
