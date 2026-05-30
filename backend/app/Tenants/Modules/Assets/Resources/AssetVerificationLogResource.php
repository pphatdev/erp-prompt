<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetVerificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'campaignId'            => $this->campaign_id,
            'assetId'               => $this->asset_id,
            'verifiedBy'            => $this->verified_by,
            'verifiedAt'            => $this->verified_at,
            'previousCondition'     => $this->previous_condition,
            'newCondition'          => $this->new_condition,
            'previousLocationId'    => $this->previous_location_id,
            'newLocationId'         => $this->new_location_id,
            'reconciliationStatus'  => $this->reconciliation_status,
            'notes'                 => $this->notes,
            'createdAt'             => $this->created_at,
        ];
    }
}
