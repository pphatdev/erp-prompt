<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'taskId'       => $this->task_id,
            'task'         => $this->relationLoaded('task') && $this->task ? [
                'id'        => $this->task->id,
                'title'     => $this->task->title,
                'status'    => $this->task->status,
                'projectId' => $this->task->project_id,
            ] : null,

            'employeeId'   => $this->employee_id,
            'employee'     => $this->relationLoaded('employee') && $this->employee ? [
                'id'         => $this->employee->id,
                'employeeId' => $this->employee->employee_id,
                'fullName'   => trim(($this->employee->first_name ?? '') . ' ' . ($this->employee->last_name ?? '')) ?: null,
            ] : null,

            'logDate'      => $this->log_date,
            'hoursWorked'  => (float) $this->hours_worked,
            'notes'        => $this->notes,

            'createdAt'    => optional($this->created_at)->toIso8601String(),
        ];
    }
}
