<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function buildIndexQuery(): Builder
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name');
    }

    /**
     * Create a new role with permissions.
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);

            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role;
        });
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update($data);

            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role;
        });
    }
}
