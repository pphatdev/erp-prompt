<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmAppointmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.appointments.read');
    }

    public function view(User $user, CrmAppointment $appt): bool
    {
        return $user->hasPermission('crm.appointments.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.appointments.write');
    }

    public function update(User $user, CrmAppointment $appt): bool
    {
        return $user->hasPermission('crm.appointments.write');
    }

    public function delete(User $user, CrmAppointment $appt): bool
    {
        return $user->hasPermission('crm.appointments.delete');
    }
}
