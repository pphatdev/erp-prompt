<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'sessionToken' => $this->session_token,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'currency' => $this->currency,
            'expiresAt' => optional($this->expires_at)->toIso8601String(),
            'convertedAt' => optional($this->converted_at)->toIso8601String(),
            'convertedOrderId' => $this->converted_order_id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'itemCount' => $this->whenLoaded('items', fn () => $this->items->count()),
        ];
    }
}
