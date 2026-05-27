<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LowStockAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'productId'       => $this->product_id,
            'product'         => $this->whenLoaded('product', fn () => [
                'id'                => $this->product->id,
                'sku'               => $this->product->sku,
                'name'              => $this->product->name,
                'totalQuantity'     => (float) $this->product->total_quantity,
                'minimumStockLevel' => (int) $this->product->minimum_stock_level,
            ]),
            'threshold'        => $this->threshold,
            'quantityAtAlert'  => (float) $this->quantity_at_alert,
            'status'           => $this->status,
            'acknowledgedAt'   => optional($this->acknowledged_at)->toIso8601String(),
            'acknowledgedById' => $this->acknowledged_by,
            'acknowledger'     => $this->whenLoaded('acknowledger', fn () => $this->acknowledger ? [
                'id'   => $this->acknowledger->id,
                'name' => $this->acknowledger->name,
            ] : null),
            'resolvedAt'       => optional($this->resolved_at)->toIso8601String(),
            'createdAt'        => optional($this->created_at)->toIso8601String(),
            'updatedAt'        => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
