<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'quizId'         => $this->quiz_id,
            'applicationId'  => $this->application_id,
            'candidateEmail' => $this->candidate_email,
            'candidateName'  => $this->candidate_name,
            'status'         => $this->status,
            'invitedAt'      => optional($this->invited_at)->toIso8601String(),
            'expiresAt'      => optional($this->expires_at)->toIso8601String(),
            'startedAt'      => optional($this->started_at)->toIso8601String(),
            'submittedAt'    => optional($this->submitted_at)->toIso8601String(),
            'score'          => $this->score === null ? null : (float) $this->score,
            'passed'         => $this->passed,
        ];
    }
}
