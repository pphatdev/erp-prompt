<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    /**
     * Listing the workforce is an admin/HR action. Self-service callers
     * read their own row via `view($self)`, not via index, so the `.self`
     * permission does NOT unlock the directory.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.employee.read');
    }

    /**
     * Self-service callers (`.self` permission) may view their own row;
     * admin/HR with `hrm.employee.read` may view anyone.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($this->ownsRow($user, $employee) && $user->hasPermission('hrm.employee.read.self')) {
            return true;
        }

        return $user->hasPermission('hrm.employee.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    /**
     * Admin updates use this gate. Self-service profile edits go through
     * {@see self::updateSelf()} via the dedicated `PATCH /me/profile`
     * endpoint — that one restricts the payload to non-sensitive fields
     * (phone, address, emergency contact) and never accepts salary/bank
     * data, regardless of the caller's intent.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hrm.employee.write');
    }

    /**
     * Self profile-edit gate. Requires `.self` AND the row belonging to
     * the caller. Admin write still works through {@see self::update()}.
     */
    public function updateSelf(User $user, Employee $employee): bool
    {
        return $this->ownsRow($user, $employee)
            && $user->hasPermission('hrm.employee.write.self');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('hrm.employee.delete');
    }

    private function ownsRow(User $user, Employee $employee): bool
    {
        $selfId = $user->employee?->id;
        return $selfId !== null && $selfId === $employee->id;
    }
}
