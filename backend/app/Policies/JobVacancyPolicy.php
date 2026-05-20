<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobVacancyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read');
    }

    public function view(User $user, JobVacancy $vacancy): bool
    {
        return $user->hasPermission('hrm.recruitment.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function update(User $user, JobVacancy $vacancy): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function delete(User $user, JobVacancy $vacancy): bool
    {
        return $user->hasPermission('hrm.recruitment.delete');
    }

    public function publish(User $user, JobVacancy $vacancy): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function close(User $user, JobVacancy $vacancy): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }
}
