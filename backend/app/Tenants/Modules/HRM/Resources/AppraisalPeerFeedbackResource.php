<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppraisalPeerFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Mirror the privacy posture of AppraisalResource — rating + free-
        // text comments are only exposed to the appraisal owner, the
        // assigned line manager, the reviewer themselves, or callers with
        // `hrm.performance.read`. Pending-rate rows are still useful for
        // counts so we always emit the meta fields (id, status, dates).
        $user = $request->user();
        $employeeId = $user?->employee?->id;

        $canSeeContent = $user?->hasPermission('hrm.performance.read')
            || $employeeId === $this->reviewer_id
            || ($this->relationLoaded('appraisal') && (
                $employeeId === $this->appraisal?->employee_id
                || $employeeId === $this->appraisal?->reviewer_id
            ));

        return [
            'id'           => $this->id,
            'appraisalId'  => $this->appraisal_id,
            'reviewerId'   => $this->reviewer_id,
            'status'       => $this->status,
            'rating'       => $canSeeContent && $this->rating !== null ? (float) $this->rating : null,
            'strengths'    => $canSeeContent ? $this->strengths : null,
            'concerns'     => $canSeeContent ? $this->concerns : null,
            'notes'        => $canSeeContent ? $this->notes : null,
            'invitedAt'    => optional($this->invited_at)->toIso8601String(),
            'submittedAt'  => optional($this->submitted_at)->toIso8601String(),
            'reviewer'     => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id'       => $this->reviewer->id,
                'fullName' => trim("{$this->reviewer->first_name} {$this->reviewer->last_name}"),
            ] : null),
            'createdAt'    => optional($this->created_at)->toIso8601String(),
            'updatedAt'    => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
