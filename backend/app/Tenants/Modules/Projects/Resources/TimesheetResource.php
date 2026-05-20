<?php

namespace App\Tenants\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetResource extends JsonResource
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
            'task_id' => $this->task_id,
            'employee_id' => $this->employee_id,
            'log_date' => $this->log_date,
            'hours_worked' => (float) $this->hours_worked,
            'notes' => $this->notes,
        ];
    }
}
