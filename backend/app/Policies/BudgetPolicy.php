<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Budget;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BudgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.budgets.read');
    }

    public function view(User $user, Budget $b): bool
    {
        return $user->hasPermission('fms.budgets.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.budgets.write');
    }

    public function update(User $user, Budget $b): bool
    {
        return $user->hasPermission('fms.budgets.write') && $b->isEditable();
    }

    public function delete(User $user, Budget $b): bool
    {
        return $user->hasPermission('fms.budgets.delete') && $b->isEditable();
    }

    public function activate(User $user, Budget $b): bool
    {
        return $user->hasPermission('fms.budgets.write') && $b->isActivatable();
    }

    public function archive(User $user, Budget $b): bool
    {
        return $user->hasPermission('fms.budgets.write') && $b->isArchivable();
    }
}
