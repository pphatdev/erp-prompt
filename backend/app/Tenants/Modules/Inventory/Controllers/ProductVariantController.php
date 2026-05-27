<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Tenants\Modules\Inventory\Resources\ProductVariantResource;
use App\Tenants\Modules\Inventory\Services\ProductVariantService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantService $service) {}

    public function index(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductVariant::class);
        return ProductVariantResource::collection($product->variants()->orderBy('name')->get());
    }

    public function store(Request $request, Product $product): ProductVariantResource|JsonResponse
    {
        Gate::authorize('create', ProductVariant::class);
        $data = $this->validatePayload($request);

        try {
            $variant = $this->service->create($product, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ProductVariantResource($variant);
    }

    public function show(ProductVariant $variant): ProductVariantResource
    {
        Gate::authorize('view', $variant);
        return new ProductVariantResource($variant);
    }

    public function update(Request $request, ProductVariant $variant): ProductVariantResource|JsonResponse
    {
        Gate::authorize('update', $variant);
        $data = $this->validatePayload($request, $variant);

        try {
            $variant = $this->service->update($variant, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ProductVariantResource($variant);
    }

    public function destroy(ProductVariant $variant): JsonResponse
    {
        Gate::authorize('delete', $variant);
        $variant->delete();
        return response()->json(['message' => 'Variant archived.']);
    }

    private function validatePayload(Request $request, ?ProductVariant $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'sku'        => "{$req}|string|max:120",
            'name'       => "{$req}|string|max:255",
            'unit_price' => 'sometimes|numeric|min:0',
            'attributes' => 'sometimes|nullable|array',
            'is_active'  => 'sometimes|boolean',
        ]);
    }
}
