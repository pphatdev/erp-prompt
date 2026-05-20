<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'interviewId'    => $this->interview_id,
            'interviewerId'  => $this->interviewer_id,
            'rating'         => $this->rating === null ? null : (float) $this->rating,
            'strengths'      => $this->strengths,
            'concerns'       => $this->concerns,
            'recommendation' => $this->recommendation,
            'submittedAt'    => optional($this->submitted_at)->toIso8601String(),
        ];
    }
}
