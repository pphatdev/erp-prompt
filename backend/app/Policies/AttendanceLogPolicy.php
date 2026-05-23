<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Attendance is dual-scoped:
 *  - admins (`hrm.attendance.read|write|delete`) see every row;
 *  - employees (`hrm.attendance.read.self`) see only their own — the
 *    controller force-filters the index by `employee_id` for non-admins
 *    so `view()` only needs to gate ownership when an admin permission
 *    isn't held.
 *  - clock-in/out is a separate `.clock.self` capability so an org can
 *    grant "read your own attendance" without granting "stamp time
 *    records" (e.g. probationary staff).
 */
class AttendanceLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.attendance.read')
            || $user->hasPermission('hrm.attendance.read.self');
    }

    public function view(User $user, AttendanceLog $log): bool
    {
        if ($user->hasPermission('hrm.attendance.read')) {
            return true;
        }

        if ($user->hasPermission('hrm.attendance.read.self')) {
            return $user->employee?->id === $log->employee_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.attendance.write');
    }

    public function update(User $user, AttendanceLog $log): bool
    {
        return $user->hasPermission('hrm.attendance.write');
    }

    public function delete(User $user, AttendanceLog $log): bool
    {
        return $user->hasPermission('hrm.attendance.delete');
    }

    public function clock(User $user): bool
    {
        return $user->hasPermission('hrm.attendance.clock.self')
            || $user->hasPermission('hrm.attendance.write');
    }
}
