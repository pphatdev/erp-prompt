<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'applicationId'   => $this->application_id,
            'quizAttemptId'   => $this->quiz_attempt_id,
            'title'           => $this->title,
            'round'           => $this->round,
            'scheduledAt'     => optional($this->scheduled_at)->toIso8601String(),
            'durationMinutes' => (int) $this->duration_minutes,
            'mode'            => $this->mode,
            'location'        => $this->location,
            'status'          => $this->status,
            'notes'           => $this->notes,
            'interviewers'    => $this->whenLoaded('interviewers', fn () => $this->interviewers->map(fn ($e) => [
                'id'        => $e->id,
                'fullName'  => trim("{$e->first_name} {$e->last_name}"),
                'email'     => $e->email,
            ])),
            'feedback'        => $this->whenLoaded('feedback', fn () => $this->feedback->map(fn ($f) => new InterviewFeedbackResource($f))),
            'averageRating'   => $this->whenLoaded('feedback', fn () => $this->averageRating()),
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
