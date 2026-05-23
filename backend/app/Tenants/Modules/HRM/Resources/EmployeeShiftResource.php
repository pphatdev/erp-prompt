<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'shiftId' => $this->shift_id,
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'shift' => $this->whenLoaded('shift', fn () => new ShiftResource($this->shift)),
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
