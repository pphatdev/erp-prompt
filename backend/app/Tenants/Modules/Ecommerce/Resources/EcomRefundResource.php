<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refundNumber' => $this->refund_number,
            'orderId' => $this->order_id,
            'paymentId' => $this->payment_id,
            'creditNoteId' => $this->credit_note_id,
            'status' => $this->status,
            'isPartial' => (bool) $this->is_partial,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'rejectionReason' => $this->rejection_reason,
            'providerRefundId' => $this->provider_refund_id,
            'requestedBy' => $this->requested_by,
            'requestedAt' => optional($this->requested_at)->toIso8601String(),
            'approvedBy' => $this->approved_by,
            'approvedAt' => optional($this->approved_at)->toIso8601String(),
            'rejectedBy' => $this->rejected_by,
            'rejectedAt' => optional($this->rejected_at)->toIso8601String(),
            'completedAt' => optional($this->completed_at)->toIso8601String(),
            'items' => EcomRefundItemResource::collection($this->whenLoaded('items')),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
