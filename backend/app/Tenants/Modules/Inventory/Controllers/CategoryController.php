<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Category;
use App\Tenants\Modules\Inventory\Resources\CategoryResource;
use App\Tenants\Modules\Inventory\Services\CategoryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    use Paginates;

    public function __construct(private readonly CategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        if ($request->boolean('tree')) {
            return response()->json(['data' => $this->service->tree()]);
        }

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('slug', 'ilike', $like));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('parent_id')) {
            $parent = $request->query('parent_id');
            if ($parent === '' || $parent === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parent);
            }
        }

        return $this->paginatedResponse(CategoryResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): CategoryResource|JsonResponse
    {
        Gate::authorize('create', Category::class);
        $data = $this->validatePayload($request);

        try {
            $c = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CategoryResource($c);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);
        return new CategoryResource($category->loadCount('products')->load('parent'));
    }

    public function update(Request $request, Category $category): CategoryResource|JsonResponse
    {
        Gate::authorize('update', $category);
        $data = $this->validatePayload($request, $category);

        try {
            $c = $this->service->update($category, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CategoryResource($c);
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        try {
            $this->service->archive($category);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Category archived.']);
    }

    private function validatePayload(Request $request, ?Category $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'name'        => "{$req}|string|max:160",
            'slug'        => 'sometimes|nullable|string|max:120',
            'description' => 'sometimes|nullable|string|max:2000',
            'color'       => 'sometimes|nullable|string|max:32',
            'sort_order'  => 'sometimes|nullable|integer|min:0',
            'is_active'   => 'sometimes|boolean',
            'parent_id'   => 'sometimes|nullable|uuid|exists:categories,id',
        ]);
    }
}
