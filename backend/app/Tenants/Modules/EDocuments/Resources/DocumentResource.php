<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use App\Tenants\Modules\IAM\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'sizeBytes' => (int) $this->size_bytes,
            'folderId' => $this->folder_id,
            'uploaderId' => $this->uploader_id,
            'documentableType' => $this->documentable_type,
            'documentableId' => $this->documentable_id,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? new UserResource($this->uploader) : null),
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? new FolderResource($this->folder) : null),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)),
            'versionsCount' => $this->when(isset($this->versions_count), fn () => (int) $this->versions_count),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
