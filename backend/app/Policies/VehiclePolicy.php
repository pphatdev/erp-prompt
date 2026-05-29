<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\User;
use App\Models\Tenant\Vehicle;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehiclePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return $user->hasPermission('fleet.vehicles.read') || $user->hasPermission('fleet.vehicles.read.self'); }
    public function view(User $user, Vehicle $v): bool { return $user->hasPermission('fleet.vehicles.read') || $user->hasPermission('fleet.vehicles.read.self'); }
    public function create(User $user): bool { return $user->hasPermission('fleet.vehicles.write'); }
    public function update(User $user, Vehicle $v): bool { return $user->hasPermission('fleet.vehicles.write'); }
    public function delete(User $user, Vehicle $v): bool { return $user->hasPermission('fleet.vehicles.delete'); }
}
