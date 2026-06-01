<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'id'           => $this->id,
            'projectId'    => $this->project_id,
            'project'      => $this->relationLoaded('project') && $this->project ? [
                'id'     => $this->project->id,
                'name'   => $this->project->name,
                'status' => $this->project->status,
            ] : null,

            'title'        => $this->title,
            'description'  => $this->description,
            'dueDate'      => $this->due_date,
            'status'       => $this->status,
            'priority'     => $this->priority,

            'assigneeId'   => $this->assignee_id,
            'assignee'     => $this->relationLoaded('assignee') && $this->assignee ? [
                'id'         => $this->assignee->id,
                'employeeId' => $this->assignee->employee_id,
                'fullName'   => trim(($this->assignee->first_name ?? '') . ' ' . ($this->assignee->last_name ?? '')) ?: null,
            ] : null,

            'createdAt'    => optional($this->created_at)->toIso8601String(),
            'updatedAt'    => optional($this->updated_at)->toIso8601String(),
        ];

        if ($this->relationLoaded('timesheets')) {
            $payload['timesheets'] = $this->timesheets
                ->map(fn ($ts) => (new TimesheetResource($ts))->toArray($request))
                ->all();
        }

        return $payload;
    }
}
