<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Tenants\Modules\Inventory\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['variants', 'modules'])->orderBy('name');

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'ilike', $like)
                  ->orWhere('sku', 'ilike', $like);
            });
        }

        if ($type = $request->query('product_type')) {
            $query->where('product_type', $type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(ProductResource::class, $paginator, $request);
    }

    public function store(Request $request): ProductResource
    {
        $validated = $request->validate([
            'sku'                  => 'required|string|max:120|unique:products,sku',
            'name'                 => 'required|string|max:255',
            'product_type'         => ['sometimes', Rule::in(Product::TYPES)],
            'description'          => 'nullable|string|max:1000',
            'description_long'     => 'nullable|string',
            'unit_price'           => 'required|numeric|min:0',
            'minimum_stock_level'  => 'nullable|integer|min:0',
            'is_active'            => 'sometimes|boolean',
            'module_ids'           => 'sometimes|array',
            'module_ids.*'         => 'uuid|exists:modules,id',
        ]);

        $product = Product::create(collect($validated)->except('module_ids')->toArray());

        if (!empty($validated['module_ids'])) {
            $product->modules()->sync($validated['module_ids']);
        }

        return new ProductResource($product->load(['variants', 'modules']));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('variants', 'modules'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $validated = $request->validate([
            'sku'                  => ['sometimes', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product->id)],
            'name'                 => 'sometimes|string|max:255',
            'product_type'         => ['sometimes', Rule::in(Product::TYPES)],
            'description'          => 'sometimes|nullable|string|max:1000',
            'description_long'     => 'sometimes|nullable|string',
            'unit_price'           => 'sometimes|numeric|min:0',
            'minimum_stock_level'  => 'sometimes|nullable|integer|min:0',
            'is_active'            => 'sometimes|boolean',
            'module_ids'           => 'sometimes|array',
            'module_ids.*'         => 'uuid|exists:modules,id',
        ]);

        $product->update(collect($validated)->except('module_ids')->toArray());

        if ($request->has('module_ids')) {
            $product->modules()->sync($validated['module_ids'] ?? []);
        }

        return new ProductResource($product->fresh(['variants', 'modules']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product archived.']);
    }
}
