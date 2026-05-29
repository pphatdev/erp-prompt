<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\User;
use App\Models\Tenant\VehicleModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehicleModelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return $user->hasPermission('fleet.vehicle_models.read'); }
    public function view(User $user, VehicleModel $m): bool { return $user->hasPermission('fleet.vehicle_models.read'); }
    public function create(User $user): bool { return $user->hasPermission('fleet.vehicle_models.write'); }
    public function update(User $user, VehicleModel $m): bool { return $user->hasPermission('fleet.vehicle_models.write'); }
    public function delete(User $user, VehicleModel $m): bool { return $user->hasPermission('fleet.vehicle_models.delete'); }
}
