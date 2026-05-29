<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'token' => $this->token,
            'expiresAt' => optional($this->expires_at)->toIso8601String(),
            'hasPassword' => $this->password_hash !== null,
            'maxDownloads' => $this->max_downloads !== null ? (int) $this->max_downloads : null,
            'downloadsCount' => (int) $this->downloads_count,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
