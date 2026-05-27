<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get('numbering.quotation_prefix') ?: 'QUO-';
        $quoteNumber = $this->quote_number;
        if ($quoteNumber && preg_match('/(\d+)$/', $quoteNumber, $matches)) {
            $quoteNumber = $prefix . $matches[1];
        }

        return [
            'id' => $this->id,
            'quoteNumber' => $quoteNumber,
            'customerId' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'quoteDate' => optional($this->quote_date)->toDateString(),
            'validUntil' => optional($this->valid_until)->toDateString(),
            'dueDate' => optional($this->due_date)->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'confirmedAt' => optional($this->confirmed_at)->toIso8601String(),
            'cancelledAt' => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'orderId' => $this->whenLoaded('order', fn () => $this->order?->id),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
