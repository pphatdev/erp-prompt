<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'date' => optional($this->date)->toDateString(),
            'hours' => (float) $this->hours,
            'rateMultiplier' => (float) $this->rate_multiplier,
            'reason' => $this->reason,
            'status' => $this->status,
            'processedBy' => $this->processed_by,
            'processedAt' => optional($this->processed_at)->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
