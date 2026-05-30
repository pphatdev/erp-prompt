<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Asset;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assets.tracking.read')
            || $user->hasPermission('assets.tracking.read.self');
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($user->hasPermission('assets.tracking.read')) {
            return true;
        }
        // Custodian self-service: read only the assets assigned to the caller.
        return $user->hasPermission('assets.tracking.read.self')
            && $user->employee_id !== null
            && (string) $user->employee_id === (string) $asset->custodian_employee_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assets.tracking.write');
    }

    public function update(User $user, Asset $asset): bool
    {
        if ($user->hasPermission('assets.tracking.write')) {
            return true;
        }
        // Custodian self-audit: condition/location updates only.
        return $user->hasPermission('assets.tracking.write.self')
            && $user->employee_id !== null
            && (string) $user->employee_id === (string) $asset->custodian_employee_id;
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.tracking.delete');
    }

    public function depreciate(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.depreciation.write');
    }

    public function revalue(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.revaluation.write');
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.disposal.write');
    }
}
