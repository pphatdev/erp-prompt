<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'gracePeriodMinutes' => (int) $this->grace_period_minutes,
            'halfDayThresholdMinutes' => $this->half_day_threshold_minutes !== null
                ? (int) $this->half_day_threshold_minutes
                : null,
            'assignmentCount' => $this->whenCounted('assignments'),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
