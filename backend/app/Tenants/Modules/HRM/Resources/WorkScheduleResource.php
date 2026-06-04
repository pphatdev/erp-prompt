<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'targetType'  => $this->target_type,
            'targetId'    => $this->target_id,
            'dayOfWeek'   => (int) $this->day_of_week,
            'isWorkDay'   => (bool) $this->is_work_day,
            'intervals'   => is_array($this->intervals) ? $this->intervals : [],
            'totalMinutes' => $this->totalMinutes(),
            'totalHours'   => $this->totalHours(),
            'createdAt'   => optional($this->created_at)->toIso8601String(),
            'updatedAt'   => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
