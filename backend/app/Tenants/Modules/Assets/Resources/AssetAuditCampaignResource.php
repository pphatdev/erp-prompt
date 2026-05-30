<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetAuditCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'frequency'           => $this->frequency,
            'startsAt'            => $this->starts_at,
            'endsAt'              => $this->ends_at,
            'status'              => $this->status,
            'assignedTo'          => $this->assigned_to,
            'expectedAssetCount'  => $this->expected_asset_count,
            'startedAt'           => $this->started_at,
            'completedAt'         => $this->completed_at,
            'reconciliation'      => $this->when(
                isset($this->reconciliation),
                fn () => $this->reconciliation,
            ),
            // Closure form — see note in AssetResource about MissingValue +
            // Resource::collection() crashing the Paginates trait's toArray() path.
            'verifications'       => $this->whenLoaded('verifications', fn () => AssetVerificationLogResource::collection($this->verifications)),
            'createdAt'           => $this->created_at,
            'updatedAt'           => $this->updated_at,
        ];
    }
}
