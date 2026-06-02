<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the full `ecommerce.*` permission catalog, attaches the admin set to
 * the existing `admin` role, and creates a dedicated `shopper` role that
 * holds storefront-read only — used by `EcomCustomer` accounts logging in
 * via the `shop` auth guard.
 *
 * Per-tenant rollout:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\EcommercePermissionSeeder" --option="force=true"
 */
class EcommercePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminPermissions = [
            // Admin: Orders
            ['name' => 'Read Ecom Orders',      'slug' => 'ecommerce.orders.read',     'module' => 'ecommerce', 'feature' => 'orders',    'action' => 'read'],
            ['name' => 'Write Ecom Orders',     'slug' => 'ecommerce.orders.write',    'module' => 'ecommerce', 'feature' => 'orders',    'action' => 'write'],
            ['name' => 'Cancel Ecom Orders',    'slug' => 'ecommerce.orders.cancel',   'module' => 'ecommerce', 'feature' => 'orders',    'action' => 'cancel'],

            // Admin: Refunds
            ['name' => 'Read Ecom Refunds',     'slug' => 'ecommerce.refunds.read',    'module' => 'ecommerce', 'feature' => 'refunds',   'action' => 'read'],
            ['name' => 'Write Ecom Refunds',    'slug' => 'ecommerce.refunds.write',   'module' => 'ecommerce', 'feature' => 'refunds',   'action' => 'write'],
            ['name' => 'Approve Ecom Refunds',  'slug' => 'ecommerce.refunds.approve', 'module' => 'ecommerce', 'feature' => 'refunds',   'action' => 'approve'],

            // Admin: Products (catalog curation — featured flag, publish/unpublish)
            ['name' => 'Read Ecom Products',    'slug' => 'ecommerce.products.read',   'module' => 'ecommerce', 'feature' => 'products',  'action' => 'read'],
            ['name' => 'Write Ecom Products',   'slug' => 'ecommerce.products.write',  'module' => 'ecommerce', 'feature' => 'products',  'action' => 'write'],

            // Admin: Customers (shopper accounts)
            ['name' => 'Read Ecom Customers',   'slug' => 'ecommerce.customers.read',  'module' => 'ecommerce', 'feature' => 'customers', 'action' => 'read'],
            ['name' => 'Write Ecom Customers',  'slug' => 'ecommerce.customers.write', 'module' => 'ecommerce', 'feature' => 'customers', 'action' => 'write'],

            // Admin: Settings (payment provider keys, storefront branding)
            ['name' => 'Read Ecom Settings',    'slug' => 'ecommerce.settings.read',   'module' => 'ecommerce', 'feature' => 'settings',  'action' => 'read'],
            ['name' => 'Write Ecom Settings',   'slug' => 'ecommerce.settings.write',  'module' => 'ecommerce', 'feature' => 'settings',  'action' => 'write'],
        ];

        $shopperPermissions = [
            ['name' => 'Read Storefront',       'slug' => 'ecommerce.storefront.read', 'module' => 'ecommerce', 'feature' => 'storefront', 'action' => 'read'],
        ];

        $adminIds = [];
        foreach ($adminPermissions as $perm) {
            $adminIds[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        $shopperIds = [];
        foreach ($shopperPermissions as $perm) {
            $shopperIds[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($adminIds, $shopperIds) {
            // Admin gets the full surface — storefront.read included so an admin
            // can preview storefront-scoped endpoints.
            $role->permissions()->syncWithoutDetaching(array_merge($adminIds, $shopperIds));
        });

        $shopperRole = Role::updateOrCreate(['slug' => 'shopper'], [
            'name' => 'Storefront Shopper',
            'description' => 'Default role for EcomCustomer accounts. Read-only access to the public storefront and their own orders/cart endpoints.',
        ]);
        $shopperRole->permissions()->syncWithoutDetaching($shopperIds);
    }
}
