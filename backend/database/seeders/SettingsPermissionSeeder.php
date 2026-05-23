<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Backfill the settings.read / settings.write permissions and attach them to
 * every admin role in all existing tenant databases.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\SettingsPermissionSeeder" --option="force=true"
 */
class SettingsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $read = Permission::updateOrCreate(
            ['slug' => 'settings.read'],
            ['name' => 'Read Settings', 'module' => 'settings', 'feature' => 'settings', 'action' => 'read']
        );

        $write = Permission::updateOrCreate(
            ['slug' => 'settings.write'],
            ['name' => 'Write Settings', 'module' => 'settings', 'feature' => 'settings', 'action' => 'write']
        );

        // Grant to every admin role (sync keeps existing grants intact)
        Role::where('slug', 'admin')->each(function (Role $role) use ($read, $write) {
            $role->permissions()->syncWithoutDetaching([$read->id, $write->id]);
        });
    }
}
