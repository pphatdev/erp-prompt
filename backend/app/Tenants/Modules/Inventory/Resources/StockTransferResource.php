<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'transferNumber'  => $this->transfer_number,
            'fromWarehouseId' => $this->from_warehouse_id,
            'toWarehouseId'   => $this->to_warehouse_id,
            'fromWarehouse'   => $this->whenLoaded('fromWarehouse', fn () => [
                'id' => $this->fromWarehouse->id, 'code' => $this->fromWarehouse->code, 'name' => $this->fromWarehouse->name,
            ]),
            'toWarehouse'     => $this->whenLoaded('toWarehouse', fn () => [
                'id' => $this->toWarehouse->id, 'code' => $this->toWarehouse->code, 'name' => $this->toWarehouse->name,
            ]),
            'status'          => $this->status,
            'initiatedAt'     => optional($this->initiated_at)->toIso8601String(),
            'dispatchedAt'    => optional($this->dispatched_at)->toIso8601String(),
            'receivedAt'      => optional($this->received_at)->toIso8601String(),
            'cancelledAt'     => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason'    => $this->cancel_reason,
            'notes'           => $this->notes,
            'items'           => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'           => $i->id,
                'productId'    => $i->product_id,
                'variantId'    => $i->variant_id,
                'quantity'     => (float) $i->quantity,
                'receivedQty'  => (float) $i->received_qty,
                'outstanding'  => max(0.0, (float) $i->quantity - (float) $i->received_qty),
                'notes'        => $i->notes,
            ])),
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
