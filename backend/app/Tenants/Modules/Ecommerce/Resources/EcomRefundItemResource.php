<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomRefundItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refundId' => $this->refund_id,
            'orderItemId' => $this->order_item_id,
            'quantity' => (float) $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'lineTotal' => (float) $this->line_total,
            'restock' => (bool) $this->restock,
        ];
    }
}
