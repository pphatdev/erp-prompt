<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\FuelLog;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FuelLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return $user->hasPermission('fleet.fuel.read'); }
    public function view(User $user, FuelLog $l): bool { return $user->hasPermission('fleet.fuel.read'); }
    // Self-service drivers can file fuel logs against their assigned vehicle;
    // create() here gates on EITHER admin write OR the self scope. Ownership
    // (driver_id === $user->employee->id) check still belongs in the service
    // layer once vehicle-driver assignments are modeled.
    public function create(User $user): bool { return $user->hasPermission('fleet.fuel.write') || $user->hasPermission('fleet.fuel.write.self'); }
    public function update(User $user, FuelLog $l): bool { return $user->hasPermission('fleet.fuel.write'); }
    public function delete(User $user, FuelLog $l): bool { return $user->hasPermission('fleet.fuel.delete'); }
}
