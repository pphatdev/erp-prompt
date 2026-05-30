<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Setting key must match what ProcurementService::generatePoNumber() writes
        // and what the Prefix Code matrix page exposes — `numbering.po_prefix`.
        // The legacy `numbering.purchase_order_prefix` key was never seeded, so the
        // fallback fired on every read and the user's prefix change had no visible
        // effect on PO numbers.
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get('numbering.po_prefix') ?: 'PO-';
        $poNumber = $this->po_number;
        if ($poNumber && preg_match('/(\d+)$/', $poNumber, $matches)) {
            $poNumber = $prefix . $matches[1];
        }

        return [
            'id'           => $this->id,
            'poNumber'     => $poNumber,
            'supplierId'   => $this->supplier_id,
            'warehouseId'  => $this->warehouse_id,
            'status'       => $this->status,
            'orderDate'    => optional($this->order_date)->toDateString(),
            'expectedAt'   => optional($this->expected_at)->toDateString(),
            'receivedAt'   => optional($this->received_at)->toIso8601String(),
            'subtotal'     => (float) $this->subtotal,
            'taxAmount'    => (float) $this->tax_amount,
            'totalAmount'  => (float) $this->total_amount,
            'notes'        => $this->notes,
            'orderedBy'    => $this->ordered_by,
            'submittedAt'  => optional($this->submitted_at)->toIso8601String(),
            'approvedBy'   => $this->approved_by,
            'approvedAt'   => optional($this->approved_at)->toIso8601String(),
            'cancelledAt'  => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'supplier'     => $this->whenLoaded('supplier', fn () => [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code,
            ]),
            'warehouse'    => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'items'        => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'           => $i->id,
                'productId'    => $i->product_id,
                'variantId'    => $i->variant_id,
                'productName'  => $i->product_name,
                'variantSku'   => $i->variant_sku,
                'orderedQty'   => (float) $i->ordered_qty,
                'receivedQty'  => (float) $i->received_qty,
                'outstandingQty' => $i->outstandingQty(),
                'unitCost'     => (float) $i->unit_cost,
                'lineTotal'    => (float) $i->line_total,
                'notes'        => $i->notes,
            ])),
            'createdAt'    => $this->created_at?->toIso8601String(),
            'updatedAt'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
