<?php

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Tenants\Modules\Inventory\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(Product::query()->orderBy('name'), $request);

        return $this->paginatedResponse(ProductResource::class, $paginator, $request);
    }

    public function store(Request $request): ProductResource
    {
        $data = $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
        ]);

        $product = Product::create($data);
        return new ProductResource($product);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
