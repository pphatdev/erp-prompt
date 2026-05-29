<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use App\Tenants\Modules\IAM\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'versionNumber' => (int) $this->version_number,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'sizeBytes' => (int) $this->size_bytes,
            'changeSummary' => $this->change_summary,
            'uploadedById' => $this->uploaded_by_id,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? new UserResource($this->uploader) : null),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
