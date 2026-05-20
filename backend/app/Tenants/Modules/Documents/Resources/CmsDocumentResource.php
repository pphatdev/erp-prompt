<?php

namespace App\Tenants\Modules\Documents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tenants\Modules\IAM\Resources\UserResource;

class CmsDocumentResource extends JsonResource
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
            'title' => $this->title,
            'cms_folder_id' => $this->cms_folder_id,
            'locked_by' => new UserResource($this->whenLoaded('lockedBy')),
            'locked_at' => $this->locked_at,
            'retention_expiry' => $this->retention_expiry,
            'latest_version' => new CmsDocumentVersionResource($this->whenLoaded('latestVersion')),
            'versions' => CmsDocumentVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
