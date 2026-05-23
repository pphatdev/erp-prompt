<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderItemId' => $this->order_item_id,
            'productId' => $this->product_id,
            'variantId' => $this->variant_id,
            'productName' => $this->product_name,
            'productType' => $this->product_type,
            'variantSku' => $this->variant_sku,
            'quantity' => (float) $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'lineTotal' => (float) $this->line_total,
        ];
    }
}
