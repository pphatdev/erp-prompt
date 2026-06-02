<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the full `calendar.event.*` permission catalog and attaches the
 * admin set to the existing `admin` role. The self-service variants are
 * granted to the `employee` role (when present) so any logged-in employee
 * can see their own calendar feed and create personal events.
 *
 * Holiday CRUD continues to live in the HRM module under `hrm.holiday.*`;
 * this seeder does NOT shadow those permissions.
 *
 * Per-tenant rollout:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\CalendarPermissionSeeder" --option="force=true"
 */
class CalendarPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminPermissions = [
            ['name' => 'Read Calendar Events',     'slug' => 'calendar.event.read',     'module' => 'calendar', 'feature' => 'event', 'action' => 'read'],
            ['name' => 'Write Calendar Events',    'slug' => 'calendar.event.write',    'module' => 'calendar', 'feature' => 'event', 'action' => 'write'],
            ['name' => 'Delete Calendar Events',   'slug' => 'calendar.event.delete',   'module' => 'calendar', 'feature' => 'event', 'action' => 'delete'],
            ['name' => 'Override Calendar Events', 'slug' => 'calendar.event.override', 'module' => 'calendar', 'feature' => 'event', 'action' => 'override'],
        ];

        $selfPermissions = [
            ['name' => 'Read Own Calendar Events',  'slug' => 'calendar.event.read.self',  'module' => 'calendar', 'feature' => 'event', 'action' => 'read.self'],
            ['name' => 'Write Own Calendar Events', 'slug' => 'calendar.event.write.self', 'module' => 'calendar', 'feature' => 'event', 'action' => 'write.self'],
        ];

        $adminIds = [];
        foreach ($adminPermissions as $perm) {
            $adminIds[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }
        $selfIds = [];
        foreach ($selfPermissions as $perm) {
            $selfIds[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($adminIds, $selfIds) {
            $role->permissions()->syncWithoutDetaching(array_merge($adminIds, $selfIds));
        });

        Role::where('slug', 'employee')->each(function (Role $role) use ($selfIds) {
            $role->permissions()->syncWithoutDetaching($selfIds);
        });
    }
}
