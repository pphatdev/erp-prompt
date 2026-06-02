<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosTerminalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'warehouseId' => $this->warehouse_id,
            'warehouseCode' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->code),
            'warehouseName' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'pettyCashAccountId' => $this->petty_cash_account_id,
            'pettyCashAccountCode' => $this->whenLoaded('pettyCashAccount', fn () => $this->pettyCashAccount?->code),
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
