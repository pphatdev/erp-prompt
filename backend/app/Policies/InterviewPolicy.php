<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Interview;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InterviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read');
    }

    /**
     * Assigned interviewers can always view an interview; others need read.
     */
    public function view(User $user, Interview $interview): bool
    {
        if ($this->isAssignedInterviewer($user, $interview)) {
            return true;
        }
        return $user->hasPermission('hrm.recruitment.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->hasPermission('hrm.recruitment.delete');
    }

    public function cancel(User $user, Interview $interview): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    public function complete(User $user, Interview $interview): bool
    {
        return $user->hasPermission('hrm.recruitment.write');
    }

    /**
     * Assigned interviewer can submit/view their own feedback;
     * `hrm.recruitment.write` lets HR write on behalf.
     */
    public function submitFeedback(User $user, Interview $interview): bool
    {
        if ($this->isAssignedInterviewer($user, $interview)) {
            return true;
        }
        return $user->hasPermission('hrm.recruitment.write');
    }

    private function isAssignedInterviewer(User $user, Interview $interview): bool
    {
        $employeeId = $user->employee?->id;
        if ($employeeId === null) {
            return false;
        }
        return $interview->interviewers()->whereKey($employeeId)->exists();
    }
}
