<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Backfill the fleet.* permissions and attach them to every admin role in all
 * existing tenant databases.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\FleetPermissionSeeder" --option="force=true"
 */
class FleetPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Vehicles
            ['name' => 'Read Vehicles',        'slug' => 'fleet.vehicles.read',       'module' => 'fleet', 'feature' => 'vehicles',    'action' => 'read'],
            ['name' => 'Write Vehicles',       'slug' => 'fleet.vehicles.write',      'module' => 'fleet', 'feature' => 'vehicles',    'action' => 'write'],
            ['name' => 'Delete Vehicles',      'slug' => 'fleet.vehicles.delete',     'module' => 'fleet', 'feature' => 'vehicles',    'action' => 'delete'],
            ['name' => 'Export Vehicles',      'slug' => 'fleet.vehicles.export',     'module' => 'fleet', 'feature' => 'vehicles',    'action' => 'export'],
            // Telematics / tracking (read-only — ingestion is a system action)
            ['name' => 'Read Tracking',        'slug' => 'fleet.tracking.read',       'module' => 'fleet', 'feature' => 'tracking',    'action' => 'read'],
            // Maintenance
            ['name' => 'Read Maintenance',     'slug' => 'fleet.maintenance.read',    'module' => 'fleet', 'feature' => 'maintenance', 'action' => 'read'],
            ['name' => 'Write Maintenance',    'slug' => 'fleet.maintenance.write',   'module' => 'fleet', 'feature' => 'maintenance', 'action' => 'write'],
            ['name' => 'Delete Maintenance',   'slug' => 'fleet.maintenance.delete',  'module' => 'fleet', 'feature' => 'maintenance', 'action' => 'delete'],
            ['name' => 'Export Maintenance',   'slug' => 'fleet.maintenance.export',  'module' => 'fleet', 'feature' => 'maintenance', 'action' => 'export'],
            // Fuel
            ['name' => 'Read Fuel Logs',       'slug' => 'fleet.fuel.read',           'module' => 'fleet', 'feature' => 'fuel',        'action' => 'read'],
            ['name' => 'Write Fuel Logs',      'slug' => 'fleet.fuel.write',          'module' => 'fleet', 'feature' => 'fuel',        'action' => 'write'],
            ['name' => 'Delete Fuel Logs',     'slug' => 'fleet.fuel.delete',         'module' => 'fleet', 'feature' => 'fuel',        'action' => 'delete'],
            ['name' => 'Export Fuel Logs',     'slug' => 'fleet.fuel.export',         'module' => 'fleet', 'feature' => 'fuel',        'action' => 'export'],
            // Vehicle model catalog (admin-managed make/model picker entries)
            ['name' => 'Read Vehicle Models',  'slug' => 'fleet.vehicle_models.read',  'module' => 'fleet', 'feature' => 'vehicle_models', 'action' => 'read'],
            ['name' => 'Write Vehicle Models', 'slug' => 'fleet.vehicle_models.write', 'module' => 'fleet', 'feature' => 'vehicle_models', 'action' => 'write'],
            ['name' => 'Delete Vehicle Models','slug' => 'fleet.vehicle_models.delete','module' => 'fleet', 'feature' => 'vehicle_models', 'action' => 'delete'],

            // Driver self-service (`.self` scope). Granted to the standard
            // employee role so drivers can read their assigned vehicle and
            // file fuel logs without admin access to the broader fleet.
            ['name' => 'Read Assigned Vehicle',  'slug' => 'fleet.vehicles.read.self', 'module' => 'fleet', 'feature' => 'vehicles', 'action' => 'read.self'],
            ['name' => 'Log Fuel for Assigned',  'slug' => 'fleet.fuel.write.self',    'module' => 'fleet', 'feature' => 'fuel',     'action' => 'write.self'],
        ];

        $ids = [];
        foreach ($permissions as $perm) {
            $ids[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($ids) {
            $role->permissions()->syncWithoutDetaching($ids);
        });

        // Attach the two .self permissions to the `employee` role too, so
        // drivers get the self-service surface out of the box.
        $selfSlugs = ['fleet.vehicles.read.self', 'fleet.fuel.write.self'];
        $selfIds = Permission::whereIn('slug', $selfSlugs)->pluck('id')->all();
        Role::where('slug', 'employee')->each(function (Role $role) use ($selfIds) {
            $role->permissions()->syncWithoutDetaching($selfIds);
        });
    }
}
