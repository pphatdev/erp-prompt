<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'paymentMethod' => $this->payment_method,
            'amount' => (float) $this->amount,
            'tendered' => $this->tendered !== null ? (float) $this->tendered : null,
            'changeDue' => (float) $this->change_due,
            'referenceNumber' => $this->reference_number,
            'currency' => $this->currency,
        ];
    }
}
