<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Module;
use App\Tenants\Modules\Settings\Resources\ModuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * @description List all active top-level modules with their children. In the seller's tenant all modules are active; in a provisioned customer tenant only the entitled modules (plus core) are active.
     * @method GET
     * @returns { JsonResponse } JSON response containing the active modules
     */
    public function index(): JsonResponse
    {
        // 3 levels deep — HRM now has groups → sub-groups → leaves
        // (e.g. hrm > hrm-employees > hrm-employees-list). The sidebar's
        // sort logic walks all three, so they all need to ship here.
        $childOrder = fn ($q) => $q->where('is_active', true)->orderBy('sort_order');
        $modules = Module::with([
            'children'          => $childOrder,
            'children.children' => $childOrder,
        ])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ModuleResource::collection($modules)]);
    }

    /**
     * @description Flat list of accessible module slugs — for quick frontend access checks.
     * @method GET
     * @returns { JsonResponse } JSON response containing module slugs
     */
    public function slugs(): JsonResponse
    {
        $slugs = Module::where('is_active', true)->pluck('slug');

        return response()->json(['data' => $slugs]);
    }

    /**
     * @description All modules (active + inactive) with children and linked products — for the settings management UI. Unlike index(), this is not filtered by is_active.
     * @method GET
     * @returns { JsonResponse } JSON response containing all modules for management
     */
    public function allForManagement(): JsonResponse
    {
        // The HRM tree is now 3 levels deep (e.g. hrm > hrm-employees > hrm-employees-list).
        // Eager-load grandchildren explicitly so the management UI can paint them.
        $modules = Module::with([
            'children'                   => fn ($q) => $q->orderBy('sort_order'),
            'children.children'          => fn ($q) => $q->orderBy('sort_order'),
            'children.children.products',
            'children.products',
            'products',
        ])
            ->whereNull('parent_id')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ModuleResource::collection($modules)]);
    }

    /**
     * @description Toggle is_active on a single module. Core modules are protected.
     * @method PUT
     * @param { Module } module The module to toggle
     * @returns { JsonResponse } JSON response containing the updated module
     */
    public function toggle(Module $module): JsonResponse
    {
        if ($module->is_core) {
            return response()->json(['message' => 'Core modules cannot be deactivated.'], 403);
        }

        $module->update(['is_active' => ! $module->is_active]);

        return response()->json(['data' => new ModuleResource($module)]);
    }
    /**
     * @description Bulk update modules visibility and sort order.
     * @method PUT
     * @param { Request } request The incoming request containing modules array
     * @returns { JsonResponse } JSON response indicating success
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modules'              => 'required|array',
            'modules.*.id'         => 'required|uuid|exists:modules,id',
            'modules.*.is_active'  => 'required|boolean',
            'modules.*.sort_order' => 'required|integer',
        ]);

        \DB::transaction(function () use ($data) {
            foreach ($data['modules'] as $modData) {
                $module = Module::find($modData['id']);
                // Core modules cannot be deactivated, force true if core
                $isActive = $module->is_core ? true : $modData['is_active'];
                
                $module->update([
                    'is_active'  => $isActive,
                    'sort_order' => $modData['sort_order'],
                ]);
            }
        });

        return response()->json(['message' => 'Modules updated successfully.']);
    }
    /**
     * @description Sync which modules a product unlocks (seller catalog management).
     * @method POST
     * @param { Request } request The incoming request containing product_ids
     * @param { Module } module The module to sync products for
     * @returns { JsonResponse } JSON response indicating success
     */
    public function syncProduct(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'product_ids'   => 'required|array',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $module->products()->sync($data['product_ids']);

        return response()->json(['message' => 'Product mapping updated.']);
    }
}
