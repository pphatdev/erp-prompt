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
     * List all active top-level modules with their children.
     * In the seller's tenant all modules are active; in a provisioned customer
     * tenant only the entitled modules (plus core) are active.
     */
    public function index(): JsonResponse
    {
        $modules = Module::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => ModuleResource::collection($modules)]);
    }

    /**
     * Flat list of accessible module slugs — for quick frontend access checks.
     */
    public function slugs(): JsonResponse
    {
        $slugs = Module::where('is_active', true)->pluck('slug');

        return response()->json(['data' => $slugs]);
    }

    /**
     * All modules (active + inactive) with children and linked products — for
     * the settings management UI. Unlike index(), this is not filtered by is_active.
     */
    public function allForManagement(): JsonResponse
    {
        $modules = Module::with([
            'children'          => fn ($q) => $q->orderBy('sort_order'),
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
     * Toggle is_active on a single module. Core modules are protected.
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
     * Sync which modules a product unlocks (seller catalog management).
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
