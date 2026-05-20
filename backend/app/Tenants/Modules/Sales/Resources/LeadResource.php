<?php

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
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
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'estimated_value' => (float) $this->estimated_value,
            'status' => $this->status,
            'source' => $this->source,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
