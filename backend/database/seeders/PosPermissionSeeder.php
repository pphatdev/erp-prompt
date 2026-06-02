<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the full `pos.*` permission catalog, attaches the admin set to the
 * existing `admin` role, and creates a `cashier` role with the operational
 * subset: open/close their own shift + take checkouts.
 *
 * Per-tenant rollout:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\PosPermissionSeeder" --option="force=true"
 */
class PosPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminPermissions = [
            // Terminal
            ['name' => 'Read POS Terminals',   'slug' => 'pos.terminal.read',   'module' => 'pos', 'feature' => 'terminal', 'action' => 'read'],
            ['name' => 'Write POS Terminals',  'slug' => 'pos.terminal.write',  'module' => 'pos', 'feature' => 'terminal', 'action' => 'write'],
            ['name' => 'Delete POS Terminals', 'slug' => 'pos.terminal.delete', 'module' => 'pos', 'feature' => 'terminal', 'action' => 'delete'],

            // Shift
            ['name' => 'Read POS Shifts',     'slug' => 'pos.shift.read',    'module' => 'pos', 'feature' => 'shift', 'action' => 'read'],
            ['name' => 'Write POS Shifts',    'slug' => 'pos.shift.write',   'module' => 'pos', 'feature' => 'shift', 'action' => 'write'],
            ['name' => 'Delete POS Shifts',   'slug' => 'pos.shift.delete',  'module' => 'pos', 'feature' => 'shift', 'action' => 'delete'],
            ['name' => 'Approve POS Variance','slug' => 'pos.shift.approve', 'module' => 'pos', 'feature' => 'shift', 'action' => 'approve'],

            // Order
            ['name' => 'Read POS Orders',  'slug' => 'pos.order.read',  'module' => 'pos', 'feature' => 'order', 'action' => 'read'],
            ['name' => 'Write POS Orders', 'slug' => 'pos.order.write', 'module' => 'pos', 'feature' => 'order', 'action' => 'write'],
            ['name' => 'Void POS Orders',  'slug' => 'pos.order.void',  'module' => 'pos', 'feature' => 'order', 'action' => 'void'],

            // Settings (account codes, defaults)
            ['name' => 'Read POS Settings',  'slug' => 'pos.settings.read',  'module' => 'pos', 'feature' => 'settings', 'action' => 'read'],
            ['name' => 'Write POS Settings', 'slug' => 'pos.settings.write', 'module' => 'pos', 'feature' => 'settings', 'action' => 'write'],
        ];

        // Cashier sees their own shift + can take checkouts; everything else
        // (variance approval, void, terminal config, settings) stays admin.
        $cashierSlugs = [
            'pos.shift.read',
            'pos.shift.write',
            'pos.order.read',
            'pos.order.write',
        ];

        $adminIds = [];
        $slugToId = [];
        foreach ($adminPermissions as $perm) {
            $row = Permission::updateOrCreate(['slug' => $perm['slug']], $perm);
            $adminIds[] = $row->id;
            $slugToId[$perm['slug']] = $row->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($adminIds) {
            $role->permissions()->syncWithoutDetaching($adminIds);
        });

        $cashierRole = Role::updateOrCreate(['slug' => 'cashier'], [
            'name' => 'Cashier',
            'description' => 'Operational POS role. Can open/close their own shift, take checkouts, and read their own orders. Cannot configure terminals, approve variance, or void sales.',
        ]);
        $cashierIds = array_values(array_intersect_key($slugToId, array_flip($cashierSlugs)));
        $cashierRole->permissions()->syncWithoutDetaching($cashierIds);
    }
}
