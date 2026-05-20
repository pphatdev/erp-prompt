<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Role;
use App\Tenants\Modules\IAM\Resources\RoleResource;
use App\Tenants\Modules\IAM\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use Paginates;

    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery($this->roleService->buildIndexQuery(), $request);

        return $this->paginatedResponse(RoleResource::class, $paginator, $request);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RoleResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:roles,slug',
            'description' => 'nullable|string',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = $this->roleService->createRole($data);
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RoleResource
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => "sometimes|string|unique:roles,slug,{$role->id}",
            'description' => 'nullable|string',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = $this->roleService->updateRole($role, $data);
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
