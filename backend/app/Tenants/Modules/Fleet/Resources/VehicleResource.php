<?php

namespace App\Tenants\Modules\Fleet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
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
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'vin' => $this->vin,
            'status' => $this->status,
            'current_mileage' => $this->current_mileage,
            'maintenance_logs' => MaintenanceLogResource::collection($this->whenLoaded('maintenanceLogs')),
            'fuel_logs' => FuelLogResource::collection($this->whenLoaded('fuelLogs')),
        ];
    }
}
