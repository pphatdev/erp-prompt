<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Backfill the assets.* permission catalogue and attach the admin scope to
 * every admin role. The custodian self-service slugs are attached to the
 * standard `employee` role so a custodian can list / self-audit their own
 * physical assets without admin reach.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\AssetsPermissionSeeder" --option="force=true"
 */
class AssetsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Tracking (Asset CRUD + custodianship)
            ['name' => 'Read Assets',        'slug' => 'assets.tracking.read',   'module' => 'assets', 'feature' => 'tracking',     'action' => 'read'],
            ['name' => 'Write Assets',       'slug' => 'assets.tracking.write',  'module' => 'assets', 'feature' => 'tracking',     'action' => 'write'],
            ['name' => 'Delete Assets',      'slug' => 'assets.tracking.delete', 'module' => 'assets', 'feature' => 'tracking',     'action' => 'delete'],
            ['name' => 'Export Assets',      'slug' => 'assets.tracking.export', 'module' => 'assets', 'feature' => 'tracking',     'action' => 'export'],

            // Depreciation
            ['name' => 'Read Depreciation',  'slug' => 'assets.depreciation.read',  'module' => 'assets', 'feature' => 'depreciation', 'action' => 'read'],
            ['name' => 'Write Depreciation', 'slug' => 'assets.depreciation.write', 'module' => 'assets', 'feature' => 'depreciation', 'action' => 'write'],
            ['name' => 'Export Depreciation','slug' => 'assets.depreciation.export','module' => 'assets', 'feature' => 'depreciation', 'action' => 'export'],

            // Revaluation
            ['name' => 'Read Revaluations',  'slug' => 'assets.revaluation.read',  'module' => 'assets', 'feature' => 'revaluation',  'action' => 'read'],
            ['name' => 'Write Revaluations', 'slug' => 'assets.revaluation.write', 'module' => 'assets', 'feature' => 'revaluation',  'action' => 'write'],
            ['name' => 'Export Revaluations','slug' => 'assets.revaluation.export','module' => 'assets', 'feature' => 'revaluation',  'action' => 'export'],

            // Disposal
            ['name' => 'Read Disposals',     'slug' => 'assets.disposal.read',  'module' => 'assets', 'feature' => 'disposal',     'action' => 'read'],
            ['name' => 'Write Disposals',    'slug' => 'assets.disposal.write', 'module' => 'assets', 'feature' => 'disposal',     'action' => 'write'],
            ['name' => 'Export Disposals',   'slug' => 'assets.disposal.export','module' => 'assets', 'feature' => 'disposal',     'action' => 'export'],

            // Physical Verification & Audit Campaigns
            ['name' => 'Read Audit Campaigns',   'slug' => 'assets.audit.read',   'module' => 'assets', 'feature' => 'audit', 'action' => 'read'],
            ['name' => 'Write Audit Campaigns',  'slug' => 'assets.audit.write',  'module' => 'assets', 'feature' => 'audit', 'action' => 'write'],
            ['name' => 'Delete Audit Campaigns', 'slug' => 'assets.audit.delete', 'module' => 'assets', 'feature' => 'audit', 'action' => 'delete'],
            ['name' => 'Export Audit Reports',   'slug' => 'assets.audit.export', 'module' => 'assets', 'feature' => 'audit', 'action' => 'export'],

            // Custodian self-service: see and self-audit assigned assets only.
            ['name' => 'Read Assigned Assets',     'slug' => 'assets.tracking.read.self',  'module' => 'assets', 'feature' => 'tracking', 'action' => 'read.self'],
            ['name' => 'Update Assigned Assets',   'slug' => 'assets.tracking.write.self', 'module' => 'assets', 'feature' => 'tracking', 'action' => 'write.self'],
            ['name' => 'Read Active Audit',        'slug' => 'assets.audit.read.self',     'module' => 'assets', 'feature' => 'audit',    'action' => 'read.self'],
            ['name' => 'Record Audit Scan',        'slug' => 'assets.audit.write.self',    'module' => 'assets', 'feature' => 'audit',    'action' => 'write.self'],
        ];

        $ids = [];
        foreach ($permissions as $perm) {
            $ids[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($ids) {
            $role->permissions()->syncWithoutDetaching($ids);
        });

        $selfSlugs = [
            'assets.tracking.read.self',
            'assets.tracking.write.self',
            'assets.audit.read.self',
            'assets.audit.write.self',
        ];
        $selfIds = Permission::whereIn('slug', $selfSlugs)->pluck('id')->all();
        Role::where('slug', 'employee')->each(function (Role $role) use ($selfIds) {
            $role->permissions()->syncWithoutDetaching($selfIds);
        });
    }
}
