<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'bankName'               => $this->bank_name,
            'branch'                 => $this->branch,
            'accountNumber'          => $this->account_number,
            'accountHolder'          => $this->account_holder,
            'swift'                  => $this->swift,
            'iban'                   => $this->iban,
            'currency'               => $this->currency,
            'openingBalance'         => (float) $this->opening_balance,
            'lastReconciledAt'       => optional($this->last_reconciled_at)->toIso8601String(),
            'lastReconciledBalance'  => $this->last_reconciled_balance !== null
                ? (float) $this->last_reconciled_balance
                : null,
            'notes'                  => $this->notes,
            'isActive'               => (bool) $this->is_active,
            'isDefault'              => (bool) $this->is_default,

            // GL linkage. `bookBalance` is the SoT — read live from accounts.balance.
            'accountId'              => $this->account_id,
            'glAccount'              => $this->whenLoaded('glAccount', fn () => [
                'id'      => $this->glAccount?->id,
                'code'    => $this->glAccount?->code,
                'name'    => $this->glAccount?->name,
                'type'    => $this->glAccount?->type,
                'balance' => (float) ($this->glAccount?->balance ?? 0),
            ]),
            'bookBalance'            => $this->bookBalance(),

            'createdAt'              => $this->created_at?->toIso8601String(),
            'updatedAt'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
