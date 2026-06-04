<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\OnboardingChecklist;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnboardingChecklistPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read')
            || $user->hasPermission('hrm.recruitment.onboarding')
            || $user->hasPermission('hrm.employee.read');
    }

    public function view(User $user, OnboardingChecklist $checklist): bool
    {
        // Owner (the linked employee) can always see their own checklist.
        if ($checklist->employee_id && $checklist->employee_id === $user->employee?->id) {
            return true;
        }
        return $this->viewAny($user);
    }
}
