<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Tenants\Modules\Inventory\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['variants', 'modules', 'category'])->orderBy('name');

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

        if ($request->has('category_id')) {
            $cat = $request->query('category_id');
            if ($cat === '' || $cat === 'null') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $cat);
            }
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
            'category_id'          => 'sometimes|nullable|uuid|exists:categories,id',
            'description'          => 'nullable|string|max:1000',
            'description_long'     => 'nullable|string',
            'unit_price'           => 'required|numeric|min:0',
            'minimum_stock_level'  => 'nullable|integer|min:0',
            'is_active'            => 'sometimes|boolean',
            'module_ids'           => 'sometimes|array',
            'module_ids.*'         => 'uuid|exists:modules,id',
            'image'                => 'nullable|image|max:2048',
        ]);

        $data = collect($validated)->except(['module_ids', 'image'])->toArray();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')
                ->store('products/' . tenant('id'), 'public');
        }

        $product = Product::create($data);

        if (!empty($validated['module_ids'])) {
            $product->modules()->sync($validated['module_ids']);
        }

        return new ProductResource($product->load(['variants', 'modules', 'category']));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('variants', 'modules', 'category'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $validated = $request->validate([
            'sku'                  => ['sometimes', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product->id)],
            'name'                 => 'sometimes|string|max:255',
            'product_type'         => ['sometimes', Rule::in(Product::TYPES)],
            'category_id'          => 'sometimes|nullable|uuid|exists:categories,id',
            'description'          => 'sometimes|nullable|string|max:1000',
            'description_long'     => 'sometimes|nullable|string',
            'unit_price'           => 'sometimes|numeric|min:0',
            'minimum_stock_level'  => 'sometimes|nullable|integer|min:0',
            'is_active'            => 'sometimes|boolean',
            'module_ids'           => 'sometimes|array',
            'module_ids.*'         => 'uuid|exists:modules,id',
            'image'                => 'nullable|image|max:2048',
        ]);

        $data = collect($validated)->except(['module_ids', 'image'])->toArray();

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')
                ->store('products/' . tenant('id'), 'public');
        }

        $product->update($data);

        if ($request->has('module_ids')) {
            $product->modules()->sync($validated['module_ids'] ?? []);
        }

        return new ProductResource($product->fresh(['variants', 'modules', 'category']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product archived.']);
    }
}
