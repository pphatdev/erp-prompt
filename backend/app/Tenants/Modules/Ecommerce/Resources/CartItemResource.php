<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cartId' => $this->cart_id,
            'productId' => $this->product_id,
            'variantId' => $this->variant_id,
            'productName' => $this->whenLoaded('product', fn () => $this->product?->name),
            'productSku' => $this->whenLoaded('product', fn () => $this->product?->sku),
            // Resolved to a public URL so the storefront can hand it straight
            // to <img src>. The raw `image_path` is a relative storage path
            // (e.g. `products/demo/abc.webp`) — passing it without prefixing
            // `/storage/` 404s.
            'productImage' => $this->whenLoaded('product', fn () => $this->product?->image_path
                ? asset('storage/' . $this->product->image_path)
                : null),
            'variantSku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'variantName' => $this->whenLoaded('variant', fn () => $this->variant?->name),
            'variantAttributes' => $this->whenLoaded('variant', fn () => $this->variant?->attributes),
            'quantity' => (float) $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'lineTotal' => (float) $this->line_total,
            'reservationId' => $this->reservation_id,
            'reservationExpiresAt' => $this->whenLoaded(
                'reservation',
                fn () => optional($this->reservation?->expires_at)->toIso8601String()
            ),
        ];
    }
}
