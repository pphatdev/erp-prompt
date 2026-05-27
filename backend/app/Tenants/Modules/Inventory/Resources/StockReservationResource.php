<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'productId'    => $this->product_id,
            'warehouseId'  => $this->warehouse_id,
            'variantId'    => $this->variant_id,
            'quantity'     => (float) $this->quantity,
            'reference'    => $this->reference,
            'status'       => $this->status,
            'expiresAt'    => $this->expires_at?->toIso8601String(),
            'committedAt'  => $this->committed_at?->toIso8601String(),
            'cancelledAt'  => $this->cancelled_at?->toIso8601String(),
            'expiredAt'    => $this->expired_at?->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'product'      => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'sku'  => $this->product->sku,
                'name' => $this->product->name,
            ]),
            'warehouse'    => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'variant'      => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id'   => $this->variant->id,
                'sku'  => $this->variant->sku,
                'name' => $this->variant->name,
            ] : null),
            'actor'        => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id'   => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'createdAt'    => $this->created_at?->toIso8601String(),
            'updatedAt'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
