<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return $user->hasPermission('fleet.maintenance.read'); }
    public function view(User $user, MaintenanceLog $l): bool { return $user->hasPermission('fleet.maintenance.read'); }
    public function create(User $user): bool { return $user->hasPermission('fleet.maintenance.write'); }
    public function update(User $user, MaintenanceLog $l): bool { return $user->hasPermission('fleet.maintenance.write'); }
    public function delete(User $user, MaintenanceLog $l): bool { return $user->hasPermission('fleet.maintenance.delete'); }
}
