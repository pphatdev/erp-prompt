<?php

namespace App\Tenants\Modules\Documents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsFolderResource extends JsonResource
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
            'children' => CmsFolderResource::collection($this->whenLoaded('children')),
            'documents' => CmsDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
