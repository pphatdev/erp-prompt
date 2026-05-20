<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeOthers = $request->user()?->can('hrm.performance.read') ?? false;
        $isOwner = $request->user()?->employee?->id === $this->employee_id;
        $isReviewer = $request->user()?->employee?->id === $this->reviewer_id;

        $visible = $canSeeOthers || $isOwner || $isReviewer;

        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'reviewerId' => $this->reviewer_id,
            'cycle' => $this->cycle,
            'periodStart' => optional($this->period_start)->toDateString(),
            'periodEnd' => optional($this->period_end)->toDateString(),
            'overallRating' => $visible && $this->overall_rating !== null
                ? (float) $this->overall_rating
                : null,
            'strengths' => $visible ? $this->strengths : null,
            'improvements' => $visible ? $this->improvements : null,
            'goals' => $visible ? ($this->goals ?? []) : null,
            'status' => $this->status,
            'submittedAt' => optional($this->submitted_at)->toIso8601String(),
            'reviewedAt' => optional($this->reviewed_at)->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? new EmployeeResource($this->reviewer) : null),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
