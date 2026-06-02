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
            'variantSku' => $this->whenLoaded('variant', fn () => $this->variant?->sku),
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
