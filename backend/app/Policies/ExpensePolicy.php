<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Expense;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fms.expenses.read');
    }

    public function view(User $user, Expense $e): bool
    {
        return $user->hasPermission('fms.expenses.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fms.expenses.write');
    }

    public function cancel(User $user, Expense $e): bool
    {
        return $user->hasPermission('fms.expenses.write') && $e->isCancellable();
    }

    // Expenses are immutable once posted. Corrections flow through cancel()
    // which posts a reversing JE — never via direct mutation or delete.
    public function update(User $user, Expense $e): bool
    {
        return false;
    }

    public function delete(User $user, Expense $e): bool
    {
        return false;
    }
}
