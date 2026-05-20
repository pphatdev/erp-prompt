<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tenants\Modules\IAM\Resources\UserResource;

class DocumentResource extends JsonResource
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
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'folder_id' => $this->folder_id,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'tags' => $this->whenLoaded('tags'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
