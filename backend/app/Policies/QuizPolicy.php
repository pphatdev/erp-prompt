<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Quiz;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuizPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.quiz.read');
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $user->hasPermission('hrm.quiz.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.quiz.write');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->hasPermission('hrm.quiz.write');
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->hasPermission('hrm.quiz.delete');
    }
}
