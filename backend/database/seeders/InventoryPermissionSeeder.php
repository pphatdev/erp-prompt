<?php
 
declare(strict_types=1);
 
namespace Database\Seeders;
 
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;
 
/**
 * Backfills all inventory.* permissions and associates them with every 'admin' role
 * in existing tenant databases.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\InventoryPermissionSeeder" --option="force=true"
 */
class InventoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Warehouse
            ['name' => 'Read Warehouses',    'slug' => 'inventory.warehouse.read',   'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'read'],
            ['name' => 'Write Warehouses',   'slug' => 'inventory.warehouse.write',  'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'write'],
            ['name' => 'Delete Warehouses',  'slug' => 'inventory.warehouse.delete', 'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'delete'],
 
            // Product Catalog
            ['name' => 'Read Catalog',       'slug' => 'inventory.product.read',     'module' => 'inventory', 'feature' => 'product',     'action' => 'read'],
            ['name' => 'Write Catalog',      'slug' => 'inventory.product.write',    'module' => 'inventory', 'feature' => 'product',     'action' => 'write'],
            ['name' => 'Delete Catalog',     'slug' => 'inventory.product.delete',   'module' => 'inventory', 'feature' => 'product',     'action' => 'delete'],
            ['name' => 'Export Catalog',     'slug' => 'inventory.product.export',   'module' => 'inventory', 'feature' => 'product',     'action' => 'export'],
 
            // Stock Ledger
            ['name' => 'Read Stock Ledger',  'slug' => 'inventory.stock.read',       'module' => 'inventory', 'feature' => 'stock',       'action' => 'read'],
            ['name' => 'Write Stock Ledger', 'slug' => 'inventory.stock.write',      'module' => 'inventory', 'feature' => 'stock',       'action' => 'write'],
            ['name' => 'Adjust Stock Ledger','slug' => 'inventory.stock.adjust',     'module' => 'inventory', 'feature' => 'stock',       'action' => 'adjust'],
 
            // Suppliers
            ['name' => 'Read Suppliers',     'slug' => 'inventory.suppliers.read',   'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'read'],
            ['name' => 'Write Suppliers',    'slug' => 'inventory.suppliers.write',  'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'write'],
            ['name' => 'Delete Suppliers',   'slug' => 'inventory.suppliers.delete', 'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'delete'],
 
            // Procurement (P2P)
            ['name' => 'Read Procurement',   'slug' => 'inventory.procurement.read', 'module' => 'inventory', 'feature' => 'procurement', 'action' => 'read'],
            ['name' => 'Write Procurement',  'slug' => 'inventory.procurement.write','module' => 'inventory', 'feature' => 'procurement', 'action' => 'write'],
            ['name' => 'Delete Procurement', 'slug' => 'inventory.procurement.delete','module' => 'inventory', 'feature' => 'procurement', 'action' => 'delete'],
            ['name' => 'Approve Procurement','slug' => 'inventory.procurement.approve','module' => 'inventory', 'feature' => 'procurement', 'action' => 'approve'],
 
            // eCommerce Sync
            ['name' => 'Read eCom Sync',     'slug' => 'inventory.ecommerce.read',   'module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'read'],
            ['name' => 'Write eCom Sync',    'slug' => 'inventory.ecommerce.write',  'module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'write'],
            ['name' => 'Reserve eCom Stock', 'slug' => 'inventory.ecommerce.reserve','module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'reserve'],

            // Low-stock Alerts (split read vs manage so an analyst can see the
            // queue without being able to acknowledge/resolve it).
            ['name' => 'View Low-stock Alerts',   'slug' => 'inventory.alerts.view',   'module' => 'inventory', 'feature' => 'alerts', 'action' => 'view'],
            ['name' => 'Manage Low-stock Alerts', 'slug' => 'inventory.alerts.manage', 'module' => 'inventory', 'feature' => 'alerts', 'action' => 'manage'],

            // Stock Transfers (inter-warehouse). One write permission covers the
            // full draft → dispatch → receive | cancel lifecycle.
            ['name' => 'Read Stock Transfers',  'slug' => 'inventory.transfer.read',  'module' => 'inventory', 'feature' => 'transfer', 'action' => 'read'],
            ['name' => 'Write Stock Transfers', 'slug' => 'inventory.transfer.write', 'module' => 'inventory', 'feature' => 'transfer', 'action' => 'write'],

            // Categories (catalogue taxonomy — hierarchical via parent_id)
            ['name' => 'Read Categories',   'slug' => 'inventory.category.read',   'module' => 'inventory', 'feature' => 'category', 'action' => 'read'],
            ['name' => 'Write Categories',  'slug' => 'inventory.category.write',  'module' => 'inventory', 'feature' => 'category', 'action' => 'write'],
            ['name' => 'Delete Categories', 'slug' => 'inventory.category.delete', 'module' => 'inventory', 'feature' => 'category', 'action' => 'delete'],
        ];
 
        $ids = [];
        foreach ($permissions as $perm) {
            $ids[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }
 
        Role::where('slug', 'admin')->each(function (Role $role) use ($ids) {
            $role->permissions()->syncWithoutDetaching($ids);
        });
    }
}
