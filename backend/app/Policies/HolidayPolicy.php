<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Holiday;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HolidayPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.holiday.read');
    }

    public function view(User $user, Holiday $h): bool
    {
        return $user->hasPermission('hrm.holiday.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.holiday.write');
    }

    public function update(User $user, Holiday $h): bool
    {
        return $user->hasPermission('hrm.holiday.write');
    }

    public function delete(User $user, Holiday $h): bool
    {
        return $user->hasPermission('hrm.holiday.delete');
    }
}
