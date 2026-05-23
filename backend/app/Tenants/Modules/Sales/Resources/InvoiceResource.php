<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'orderId' => $this->order_id,
            'customerId' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'invoiceDate' => optional($this->invoice_date)->toDateString(),
            'dueDate' => optional($this->due_date)->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'paidAmount' => (float) $this->paid_amount,
            'journalEntryId' => $this->journal_entry_id,
            'confirmedAt' => optional($this->confirmed_at)->toIso8601String(),
            'cancelledAt' => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
