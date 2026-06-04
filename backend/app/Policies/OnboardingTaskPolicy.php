<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\OnboardingTask;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Tasks are role-keyed. The IT lead with `hrm.recruitment.onboarding`
 * sees the IT tasks; HR / Finance leads with the same permission see
 * theirs. Until the per-role assignment lands, the controller does not
 * filter by role — that's a UI concern.
 */
class OnboardingTaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read')
            || $user->hasPermission('hrm.recruitment.onboarding')
            || $user->hasPermission('hrm.employee.read');
    }

    public function view(User $user, OnboardingTask $task): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OnboardingTask $task): bool
    {
        return $user->hasPermission('hrm.recruitment.onboarding')
            || $user->hasPermission('hrm.recruitment.write');
    }
}
