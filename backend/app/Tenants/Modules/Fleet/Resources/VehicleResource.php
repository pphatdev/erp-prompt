<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registrationNumber' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'year' => (int) $this->year,
            'vin' => $this->vin,
            'status' => $this->status,
            'currentMileage' => (int) $this->current_mileage,
            // Public-asset URL (mirrors EmployeeResource::imageUrl). Falls back
            // to null when no photo has been uploaded; the frontend renders an
            // initials/placeholder tile in that case.
            'imageUrl' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            // Closure form of whenLoaded — calling Resource::collection() on
            // the MissingValue placeholder crashes inside Laravel's
            // ResourceCollection (it tries to map() over it). Only emit these
            // keys when the relations were actually eager-loaded (show()).
            'maintenanceLogs' => $this->whenLoaded('maintenanceLogs', fn () => MaintenanceLogResource::collection($this->maintenanceLogs)),
            'fuelLogs' => $this->whenLoaded('fuelLogs', fn () => FuelLogResource::collection($this->fuelLogs)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
