<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

class ApprovalsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Requests
            ['slug' => 'approvals.requests.read', 'name' => 'Read Approval Requests', 'module' => 'approvals', 'feature' => 'requests', 'action' => 'read'],
            ['slug' => 'approvals.requests.write', 'name' => 'Write Approval Requests', 'module' => 'approvals', 'feature' => 'requests', 'action' => 'write'],
            ['slug' => 'approvals.requests.export', 'name' => 'Export Approval Requests', 'module' => 'approvals', 'feature' => 'requests', 'action' => 'export'],
            
            // Actions
            ['slug' => 'approvals.actions.read', 'name' => 'Read Approval Actions', 'module' => 'approvals', 'feature' => 'actions', 'action' => 'read'],
            ['slug' => 'approvals.actions.execute', 'name' => 'Execute Approval Actions', 'module' => 'approvals', 'feature' => 'actions', 'action' => 'execute'],
            
            // Workflows
            ['slug' => 'approvals.workflows.read', 'name' => 'Read Approval Workflows', 'module' => 'approvals', 'feature' => 'workflows', 'action' => 'read'],
            ['slug' => 'approvals.workflows.write', 'name' => 'Write Approval Workflows', 'module' => 'approvals', 'feature' => 'workflows', 'action' => 'write'],
            ['slug' => 'approvals.workflows.delete', 'name' => 'Delete Approval Workflows', 'module' => 'approvals', 'feature' => 'workflows', 'action' => 'delete'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $model = Permission::updateOrCreate(['slug' => $perm['slug']], $perm);
            $permissionIds[] = $model->id;
        }

        // Grant to admin role
        Role::where('slug', 'admin')->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }
}
