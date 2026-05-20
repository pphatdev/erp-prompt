<?php

namespace App\Tenants\Modules\Documents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tenants\Modules\IAM\Resources\UserResource;

class CmsDocumentVersionResource extends JsonResource
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
            'version_number' => $this->version_number,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'change_summary' => $this->change_summary,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
