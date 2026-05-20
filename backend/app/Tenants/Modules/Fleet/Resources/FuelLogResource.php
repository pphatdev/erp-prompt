<?php

namespace App\Tenants\Modules\Fleet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelLogResource extends JsonResource
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
            'fill_date' => $this->fill_date,
            'liters' => (float) $this->liters,
            'cost' => (float) $this->cost,
            'mileage_at_fill' => $this->mileage_at_fill,
            'driver_id' => $this->driver_id,
        ];
    }
}
