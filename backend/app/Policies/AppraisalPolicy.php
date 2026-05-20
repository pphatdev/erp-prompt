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
        return $user->hasPermission('hrm.performance.read');
    }

    /**
     * The reviewee and the assigned reviewer can always view the appraisal;
     * everyone else needs hrm.performance.read.
     */
    public function view(User $user, Appraisal $appraisal): bool
    {
        if ($this->isOwnerOrReviewer($user, $appraisal)) {
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
     * The reviewee submits their own self-assessment; HR/managers can submit
     * on behalf when needed.
     */
    public function submit(User $user, Appraisal $appraisal): bool
    {
        if ($user->employee?->id === $appraisal->employee_id) {
            return true;
        }
        return $user->hasPermission('hrm.performance.write');
    }

    /**
     * The assigned reviewer scores submitted appraisals; HR can override.
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
