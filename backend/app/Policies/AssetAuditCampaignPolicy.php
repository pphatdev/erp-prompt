<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetAuditCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assets.audit.read')
            || $user->hasPermission('assets.audit.read.self');
    }

    public function view(User $user, AssetAuditCampaign $campaign): bool
    {
        // Campaigns aren't directly scoped to an employee — the .self scope
        // means "I can see campaigns I might need to scan for". The per-asset
        // visibility is enforced separately by AssetPolicy when the custodian
        // hits a specific asset's profile.
        return $user->hasPermission('assets.audit.read')
            || $user->hasPermission('assets.audit.read.self');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assets.audit.write');
    }

    public function update(User $user, AssetAuditCampaign $campaign): bool
    {
        return $user->hasPermission('assets.audit.write');
    }

    public function delete(User $user, AssetAuditCampaign $campaign): bool
    {
        return $user->hasPermission('assets.audit.delete');
    }

    public function start(User $user, AssetAuditCampaign $campaign): bool
    {
        return $user->hasPermission('assets.audit.write');
    }

    public function complete(User $user, AssetAuditCampaign $campaign): bool
    {
        return $user->hasPermission('assets.audit.write');
    }
}
