<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillPaymentApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'billId'          => $this->bill_id,
            'bill'            => $this->whenLoaded('bill', fn () => [
                'id'                => $this->bill?->id,
                'billNumber'        => $this->bill?->bill_number,
                'total'             => (float) ($this->bill?->total ?? 0),
                'paidAmount'        => (float) ($this->bill?->paid_amount ?? 0),
                'outstandingAmount' => $this->bill?->outstandingAmount() ?? 0,
                'status'            => $this->bill?->status,
            ]),
            'appliedAmount'   => (float) $this->applied_amount,
        ];
    }
}
