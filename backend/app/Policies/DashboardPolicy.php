<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Dashboard;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Dashboards are user-scoped (own dashboards + the tenant's `is_default`
 * dashboards). Read/export require the matching `reporting.dashboard.*`
 * permissions; write/delete additionally require ownership of the row —
 * a Dashboard Viewer can't edit someone else's analytics view even if a
 * future policy grants them write access.
 */
class DashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reporting.dashboard.read');
    }

    public function view(User $user, Dashboard $dashboard): bool
    {
        if (!$user->hasPermission('reporting.dashboard.read')) {
            return false;
        }

        // Visible if the row is the tenant default OR belongs to the caller.
        return $dashboard->is_default || $dashboard->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reporting.dashboard.write');
    }

    public function update(User $user, Dashboard $dashboard): bool
    {
        return $user->hasPermission('reporting.dashboard.write')
            && $dashboard->user_id === $user->id;
    }

    public function delete(User $user, Dashboard $dashboard): bool
    {
        return $user->hasPermission('reporting.dashboard.delete')
            && $dashboard->user_id === $user->id;
    }

    public function export(User $user, Dashboard $dashboard): bool
    {
        return $user->hasPermission('reporting.dashboard.export')
            && ($dashboard->is_default || $dashboard->user_id === $user->id);
    }
}
