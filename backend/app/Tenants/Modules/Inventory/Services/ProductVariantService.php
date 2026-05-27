<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use DomainException;

class ProductVariantService
{
    public function create(Product $product, array $data): ProductVariant
    {
        $this->assertSkuUnique($data['sku']);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku'        => $data['sku'],
            'name'       => $data['name'],
            'unit_price' => $data['unit_price'] ?? $product->unit_price,
            'attributes' => $data['attributes'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        if (array_key_exists('sku', $data) && $data['sku'] !== $variant->sku) {
            $this->assertSkuUnique($data['sku'], $variant->id);
        }

        $variant->update(array_filter([
            'sku'        => $data['sku']        ?? null,
            'name'       => $data['name']       ?? null,
            'unit_price' => $data['unit_price'] ?? null,
            'is_active'  => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('attributes', $data)) {
            $variant->attributes = $data['attributes'];
            $variant->save();
        }

        return $variant->fresh();
    }

    // SKUs are shared with the products table — barcode lookups span both.
    private function assertSkuUnique(string $sku, ?string $ignoreId = null): void
    {
        $variantClash = ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        $productClash = Product::query()->where('sku', $sku)->exists();

        if ($variantClash || $productClash) {
            throw new DomainException("SKU '{$sku}' is already in use.");
        }
    }
}
