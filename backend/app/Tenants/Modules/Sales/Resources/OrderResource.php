<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'quotationId' => $this->quotation_id,
            'customerId' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'dueDate' => optional($this->due_date)->toDateString(),
            'orderedAt' => optional($this->ordered_at)->toIso8601String(),
            'confirmedAt' => optional($this->confirmed_at)->toIso8601String(),
            'cancelledAt' => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'invoiceId' => $this->whenLoaded('invoice', fn () => $this->invoice?->id),
            'subscriptionId' => $this->whenLoaded('subscription', fn () => $this->subscription?->id),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
