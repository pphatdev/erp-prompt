<?php

namespace App\Tenants\Modules\EDocuments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
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
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at->toIso8601String(),
            'children' => FolderResource::collection($this->whenLoaded('children')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
