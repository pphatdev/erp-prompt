<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'offerId'              => $this->offer_id,
            'employeeId'           => $this->employee_id,
            'name'                 => $this->name,
            'status'               => $this->status,
            'totalTasks'           => (int) $this->total_tasks,
            'completedTasks'       => (int) $this->completed_tasks,
            'progressPercent'      => $this->progressPercent(),
            'targetCompletionDate' => optional($this->target_completion_date)->toDateString(),
            'completedAt'          => optional($this->completed_at)->toIso8601String(),
            'tasks'                => $this->whenLoaded('tasks', fn () => OnboardingTaskResource::collection($this->tasks)),
            'createdAt'            => optional($this->created_at)->toIso8601String(),
            'updatedAt'            => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
