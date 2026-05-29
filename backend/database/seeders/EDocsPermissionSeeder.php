<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Backfill the edocs.* permissions and attach them to every admin role in all
 * existing tenant databases.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\EDocsPermissionSeeder" --option="force=true"
 */
class EDocsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Read Policies',     'slug' => 'edocs.policies.read',    'module' => 'edocs', 'feature' => 'policies', 'action' => 'read'],
            ['name' => 'Write Policies',    'slug' => 'edocs.policies.write',   'module' => 'edocs', 'feature' => 'policies', 'action' => 'write'],
            ['name' => 'Delete Policies',   'slug' => 'edocs.policies.delete',  'module' => 'edocs', 'feature' => 'policies', 'action' => 'delete'],
            ['name' => 'Share Policies',    'slug' => 'edocs.policies.share',   'module' => 'edocs', 'feature' => 'policies', 'action' => 'share'],

            ['name' => 'Read Explorer',     'slug' => 'edocs.explorer.read',    'module' => 'edocs', 'feature' => 'explorer', 'action' => 'read'],
            ['name' => 'Write Explorer',    'slug' => 'edocs.explorer.write',   'module' => 'edocs', 'feature' => 'explorer', 'action' => 'write'],
            ['name' => 'Delete Explorer',   'slug' => 'edocs.explorer.delete',  'module' => 'edocs', 'feature' => 'explorer', 'action' => 'delete'],
            ['name' => 'Share Explorer',    'slug' => 'edocs.explorer.share',   'module' => 'edocs', 'feature' => 'explorer', 'action' => 'share'],

            ['name' => 'Search Documents',  'slug' => 'edocs.search.read',      'module' => 'edocs', 'feature' => 'search',   'action' => 'read'],

            ['name' => 'Read Doc Tags',     'slug' => 'edocs.tags.read',        'module' => 'edocs', 'feature' => 'tags',     'action' => 'read'],
            ['name' => 'Write Doc Tags',    'slug' => 'edocs.tags.write',       'module' => 'edocs', 'feature' => 'tags',     'action' => 'write'],
            ['name' => 'Delete Doc Tags',   'slug' => 'edocs.tags.delete',      'module' => 'edocs', 'feature' => 'tags',     'action' => 'delete'],
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
