<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'shiftId' => $this->shift_id,
            'terminalId' => $this->terminal_id,
            'cashierId' => $this->cashier_id,
            'cashierName' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'clientUuid' => $this->client_uuid,
            'customerId' => $this->customer_id,
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'subtotal' => (float) $this->subtotal,
            'discountTotal' => (float) $this->discount_total,
            'taxTotal' => (float) $this->tax_total,
            'grandTotal' => (float) $this->grand_total,
            'currency' => $this->currency,
            'status' => $this->status,
            'journalEntryId' => $this->journal_entry_id,
            'voidJournalEntryId' => $this->void_journal_entry_id,
            'placedAt' => optional($this->placed_at)->toIso8601String(),
            'voidedAt' => optional($this->voided_at)->toIso8601String(),
            'voidedBy' => $this->voided_by,
            'voidReason' => $this->void_reason,
            'notes' => $this->notes,
            'items' => PosOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PosPaymentResource::collection($this->whenLoaded('payments')),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
