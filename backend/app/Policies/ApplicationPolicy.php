<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Application;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read');
    }

    public function view(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.read');
    }

    /**
     * Submitting an application is open to any authenticated principal — the
     * route is currently behind `auth:api` for staff; the future candidate
     * magic-link flow (Phase 6) uses a scoped token guard, not this Policy.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function transition(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function update(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    /**
     * Converting a hired application into an Employee touches both modules,
     * so it requires recruitment write AND employee write. This stops
     * recruiters without HR onboarding rights from creating employees.
     */
    public function convert(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.write')
            && $user->hasPermission('hrm.employee.write');
    }

    /**
     * Reverting a conversion soft-deletes the linked employee, so it requires
     * the same broad write authority as `convert` plus employee delete rights.
     * The time-window check lives in the service (≤ 7 days).
     */
    public function revertConversion(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.write')
            && $user->hasPermission('hrm.employee.delete');
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->hasPermission('hrm.recruitment.delete');
    }
}
