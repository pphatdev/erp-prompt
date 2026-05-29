<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicleId' => $this->vehicle_id,
            'serviceType' => $this->service_type,
            'serviceDate' => optional($this->service_date)->toDateString(),
            'mileageAtService' => (int) $this->mileage_at_service,
            'cost' => (float) $this->cost,
            'notes' => $this->notes,
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
