<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tasksCount = $this->tasks_count ?? ($this->relationLoaded('tasks') ? $this->tasks->count() : null);

        $payload = [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'startDate'    => $this->start_date,
            'endDate'      => $this->end_date,
            'budget'       => (float) $this->budget,
            'status'       => $this->status,

            'managerId'    => $this->manager_id,
            'manager'      => $this->relationLoaded('manager') && $this->manager ? [
                'id'         => $this->manager->id,
                'employeeId' => $this->manager->employee_id,
                'fullName'   => trim(($this->manager->first_name ?? '') . ' ' . ($this->manager->last_name ?? '')) ?: null,
            ] : null,

            'tasksCount'   => $tasksCount,

            'createdAt'    => optional($this->created_at)->toIso8601String(),
            'updatedAt'    => optional($this->updated_at)->toIso8601String(),
        ];

        if ($this->relationLoaded('tasks')) {
            $payload['tasks'] = $this->tasks
                ->map(fn ($t) => (new TaskResource($t))->toArray($request))
                ->all();
        }

        return $payload;
    }
}
