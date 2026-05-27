<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;

/**
 * Single source of truth for catalog price resolution. Any sales surface
 * (Quotation, Order, Subscription change-plan) MUST go through this
 * service so the precedence rules can't drift across modules.
 *
 * Precedence (low → high):
 *   1. Product.unit_price                (the catalog default)
 *   2. Variant.unit_price                (when caller targets a specific variant)
 *   3. Caller-supplied override          (manual discount / promo / negotiated price)
 *
 * The override takes ultimate precedence so a salesperson can hand-edit a
 * line, but the variant rung is non-optional — picking a variant always
 * adopts its price baseline unless the override is also passed.
 *
 * Resolution returns a small DTO-shaped array so the caller doesn't have
 * to remember which fields snapshot onto the line.
 */
class PricingService
{
    /**
     * @param array{
     *     product_id: string,
     *     variant_id?: string|null,
     *     unit_price?: float|int|string|null
     * } $row
     *
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
     *     unit_price: float,
     *     variant_sku: string|null
     * }
     */
    public function resolveLine(array $row): array
    {
        /** @var Product $product */
        $product = Product::findOrFail($row['product_id']);

        $variant     = null;
        $variantSku  = null;
        $unitPrice   = (float) $product->unit_price;

        if (!empty($row['variant_id'])) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::where('product_id', $product->id)
                ->findOrFail($row['variant_id']);
            $unitPrice  = (float) $variant->unit_price;
            $variantSku = $variant->sku;
        }

        if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null) {
            $unitPrice = (float) $row['unit_price'];
        }

        return [
            'product'     => $product,
            'variant'     => $variant,
            'unit_price'  => $unitPrice,
            'variant_sku' => $variantSku,
        ];
    }

    /**
     * Convenience helper for the change-plan path that doesn't construct a
     * row dict — it has product_id and optional variant_id already loose.
     */
    public function resolveForProduct(
        string $productId,
        ?string $variantId = null,
        ?float $overridePrice = null,
    ): array {
        $row = ['product_id' => $productId];
        if ($variantId !== null)     $row['variant_id'] = $variantId;
        if ($overridePrice !== null) $row['unit_price'] = $overridePrice;
        return $this->resolveLine($row);
    }
}
