<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\PosShift;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosShiftPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool         { return $user->hasPermission('pos.shift.read'); }
    public function view(User $user, PosShift $s): bool
    {
        // Cashiers can always read their own shifts; admins read all.
        return $user->hasPermission('pos.shift.read')
            || $s->cashier_id === $user->id;
    }
    public function create(User $user): bool          { return $user->hasPermission('pos.shift.write'); }
    public function update(User $user, PosShift $s): bool
    {
        // Cashier can close their own open shift; manager can close any.
        if ($s->cashier_id === $user->id && $user->hasPermission('pos.shift.write')) {
            return true;
        }
        return $user->hasPermission('pos.shift.write');
    }
    public function approve(User $user, PosShift $s): bool { return $user->hasPermission('pos.shift.approve'); }
    public function delete(User $user, PosShift $s): bool  { return $user->hasPermission('pos.shift.delete'); }
}
