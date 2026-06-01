<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashAdvanceSettlementLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'accountId'         => $this->account_id,
            'account'           => $this->whenLoaded('account', fn () => [
                'id'   => $this->account?->id,
                'code' => $this->account?->code,
                'name' => $this->account?->name,
                'type' => $this->account?->type,
            ]),
            'description'       => $this->description,
            'amount'            => (float) $this->amount,
            'receiptAttachment' => $this->receipt_attachment,
        ];
    }
}
