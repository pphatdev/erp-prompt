<?php

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
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
            'reference_number' => $this->reference_number,
            'description' => $this->description,
            'entry_date' => $this->entry_date,
            'status' => $this->status,
            'lines' => LedgerEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
