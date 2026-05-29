<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use App\Tenants\Modules\IAM\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentAcknowledgementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'userId' => $this->user_id,
            'acknowledgedAt' => optional($this->acknowledged_at)->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
        ];
    }
}
