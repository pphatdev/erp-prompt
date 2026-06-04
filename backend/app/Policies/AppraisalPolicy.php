<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Appraisal;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppraisalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.performance.read')
            || $user->hasPermission('hrm.performance.read.self');
    }

    /**
     * The reviewee and assigned reviewer can view via `.self` scope; HR/admin
     * with `hrm.performance.read` can view anyone's.
     */
    public function view(User $user, Appraisal $appraisal): bool
    {
        if ($this->isOwnerOrReviewer($user, $appraisal)
            && $user->hasPermission('hrm.performance.read.self')
        ) {
            return true;
        }

        return $user->hasPermission('hrm.performance.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.performance.write');
    }

    public function update(User $user, Appraisal $appraisal): bool
    {
        return $user->hasPermission('hrm.performance.write');
    }

    /**
     * Reviewee submits their own self-assessment with the dedicated
     * `submit.self` permission; HR can submit on behalf via the broader
     * write permission.
     */
    public function submit(User $user, Appraisal $appraisal): bool
    {
        if ($user->employee?->id === $appraisal->employee_id
            && $user->hasPermission('hrm.performance.submit.self')
        ) {
            return true;
        }

        return $user->hasPermission('hrm.performance.write');
    }

    /**
     * Reviewer scoring stays as a privileged-by-relationship bypass —
     * being the assigned reviewer is itself the grant. HR override still
     * works via `hrm.performance.write`.
     */
    public function review(User $user, Appraisal $appraisal): bool
    {
        if ($user->employee?->id === $appraisal->reviewer_id) {
            return true;
        }

        return $user->hasPermission('hrm.performance.write');
    }

    public function close(User $user, Appraisal $appraisal): bool
    {
        return $user->hasPermission('hrm.performance.write');
    }

    /**
     * Inviting peer reviewers (Phase 4 360-degree feedback). The line
     * manager assigned to the appraisal can invite peers in addition to
     * holders of the dedicated `hrm.performance.peer_review` slug and the
     * broader `hrm.performance.write` grant.
     */
    public function inviteReviewer(User $user, Appraisal $appraisal): bool
    {
        if ($user->employee?->id === $appraisal->reviewer_id) {
            return true;
        }
        return $user->hasPermission('hrm.performance.peer_review')
            || $user->hasPermission('hrm.performance.write');
    }

    /**
     * Performance has no dedicated delete permission per rules.md — write-level
     * authority covers archive. Closed appraisals are blocked at the controller.
     */
    public function delete(User $user, Appraisal $appraisal): bool
    {
        return $user->hasPermission('hrm.performance.write');
    }

    private function isOwnerOrReviewer(User $user, Appraisal $appraisal): bool
    {
        $employeeId = $user->employee?->id;
        if ($employeeId === null) {
            return false;
        }
        return $employeeId === $appraisal->employee_id || $employeeId === $appraisal->reviewer_id;
    }
}
