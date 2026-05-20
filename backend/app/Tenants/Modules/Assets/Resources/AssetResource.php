<?php

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_tag' => $this->asset_tag,
            'name' => $this->name,
            'category' => $this->category,
            'purchase_date' => $this->purchase_date,
            'purchase_cost' => (float) $this->purchase_cost,
            'current_value' => (float) $this->current_value,
            'salvage_value' => (float) $this->salvage_value,
            'useful_life_years' => $this->useful_life_years,
            'depreciation_method' => $this->depreciation_method,
            'status' => $this->status,
            'custodian_id' => $this->custodian_id, // Could load relation
            'location_id' => $this->location_id,
            'depreciation_logs' => DepreciationLogResource::collection($this->whenLoaded('depreciationLogs')),
        ];
    }
}
