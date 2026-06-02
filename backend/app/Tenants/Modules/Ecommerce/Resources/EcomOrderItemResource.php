<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'productId' => $this->product_id,
            'variantId' => $this->variant_id,
            'productName' => $this->product_name,
            'productSku' => $this->product_sku,
            'variantSku' => $this->variant_sku,
            'quantity' => (float) $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'lineTotal' => (float) $this->line_total,
        ];
    }
}
