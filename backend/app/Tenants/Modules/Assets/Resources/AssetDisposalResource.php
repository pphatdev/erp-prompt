<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetDisposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'assetId'        => $this->asset_id,
            'disposalDate'   => $this->disposal_date,
            'disposalType'   => $this->disposal_type,
            'salePrice'      => (float) $this->sale_price,
            'finalNbv'       => (float) $this->final_nbv,
            'gainLoss'       => (float) $this->gain_loss,
            'gainLossType'   => $this->gain_loss_type,
            'journalEntryId' => $this->journal_entry_id,
            'notes'          => $this->notes,
            'createdAt'      => $this->created_at,
        ];
    }
}
