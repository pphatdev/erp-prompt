<?php

namespace App\Tenants\Modules\Fleet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceLogResource extends JsonResource
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
            'vehicle_id' => $this->vehicle_id,
            'service_type' => $this->service_type,
            'service_date' => $this->service_date,
            'mileage_at_service' => $this->mileage_at_service,
            'cost' => (float) $this->cost,
            'notes' => $this->notes,
        ];
    }
}
