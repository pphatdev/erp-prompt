<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobVacancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'departmentId' => $this->department_id,
            'positionId' => $this->position_id,
            'description' => $this->description,
            'location' => $this->location,
            'employmentType' => $this->employment_type,
            'experienceMinYears' => $this->experience_min_years,
            'experienceMaxYears' => $this->experience_max_years,
            'salaryMin' => $this->salary_min !== null ? (float) $this->salary_min : null,
            'salaryMax' => $this->salary_max !== null ? (float) $this->salary_max : null,
            'vacanciesCount' => (int) $this->vacancies_count,
            'status' => $this->status,
            'postedAt' => optional($this->posted_at)->toDateString(),
            'closesAt' => optional($this->closes_at)->toDateString(),
            'department' => $this->whenLoaded('department', fn () => $this->department ? new DepartmentResource($this->department) : null),
            'position' => $this->whenLoaded('position', fn () => $this->position ? new PositionResource($this->position) : null),
            'applicationCount' => $this->whenCounted('applications'),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
