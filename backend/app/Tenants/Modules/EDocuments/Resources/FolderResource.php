<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parentId' => $this->parent_id,
            'childrenCount' => $this->when(isset($this->children_count), fn () => (int) $this->children_count),
            'documentsCount' => $this->when(isset($this->documents_count), fn () => (int) $this->documents_count),
            'children' => $this->whenLoaded('children', fn () => FolderResource::collection($this->children)),
            'documents' => $this->whenLoaded('documents', fn () => DocumentResource::collection($this->documents)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
