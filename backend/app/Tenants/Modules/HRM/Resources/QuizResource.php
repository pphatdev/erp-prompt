<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use App\Models\Tenant\QuizQuestion;
use App\Tenants\Modules\HRM\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeAnswers = $request->user()?->can('hrm.quiz.write') ?? false;

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'timeLimitMinutes'=> $this->time_limit_minutes,
            'passScore'       => $this->pass_score === null ? null : (float) $this->pass_score,
            'status'          => $this->status,
            'questions'       => $this->whenLoaded('questions', fn () => $this->questions->map(
                fn (QuizQuestion $q) => $this->transformQuestion($q, $canSeeAnswers)
            )),
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function transformQuestion(QuizQuestion $q, bool $includeAnswer): array
    {
        $row = [
            'id'           => $q->id,
            'sequence'     => $q->sequence,
            'prompt'       => $q->prompt,
            'questionType' => $q->question_type,
            'options'      => $q->options,
            'points'       => $q->points,
        ];

        if ($includeAnswer) {
            $row['correctAnswer'] = app(QuizService::class)->correctAnswerFor($q);
        }

        return $row;
    }
}
