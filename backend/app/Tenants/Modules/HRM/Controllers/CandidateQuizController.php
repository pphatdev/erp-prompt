<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\QuizAttempt;
use App\Models\Tenant\QuizQuestion;
use App\Tenants\Modules\HRM\Resources\QuizAttemptResource;
use App\Tenants\Modules\HRM\Services\QuizService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candidate-facing endpoints. These run OUTSIDE the auth:api middleware;
 * authorization is the magic-link token in ?token=... query string. Every
 * action resolves the attempt via the token hash — no token, no access.
 */
class CandidateQuizController extends Controller
{
    public function __construct(private readonly QuizService $quizzes)
    {
    }

    /**
     * Initial magic-link landing. Returns the attempt + a sanitised quiz
     * (questions WITHOUT correct answers) so the frontend can render the
     * sandboxed assessment view.
     */
    public function auth(Request $request): JsonResponse
    {
        $attempt = $this->resolveAttempt($request);

        return response()->json([
            'data' => [
                'attempt' => new QuizAttemptResource($attempt),
                'quiz'    => $this->sanitisedQuiz($attempt),
            ],
        ]);
    }

    /**
     * Idempotent start: flips invited → in_progress, stamps started_at.
     */
    public function start(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->assertOwnsToken($request, $attempt);

        try {
            $attempt = $this->quizzes->startAttempt($attempt);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new QuizAttemptResource($attempt)]);
    }

    /**
     * Submit answers and auto-grade. Returns the graded attempt — the
     * candidate sees the score, the linked Application is updated to
     * `assessment_completed`, and the attempt's status becomes `completed`.
     */
    public function submit(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->assertOwnsToken($request, $attempt);

        $data = $request->validate([
            'answers'   => 'required|array',
            'answers.*' => 'nullable',
        ]);

        try {
            $attempt = $this->quizzes->submitAttempt($attempt, $data['answers']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new QuizAttemptResource($attempt)]);
    }

    private function resolveAttempt(Request $request): QuizAttempt
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            abort(401, 'Missing token.');
        }

        $attempt = $this->quizzes->findAttemptByToken($token);
        if (!$attempt) {
            abort(404, 'Invalid or expired token.');
        }

        return $attempt;
    }

    private function assertOwnsToken(Request $request, QuizAttempt $attempt): void
    {
        $resolved = $this->resolveAttempt($request);
        if ($resolved->id !== $attempt->id) {
            abort(403, 'Token does not match the requested attempt.');
        }
    }

    private function sanitisedQuiz(QuizAttempt $attempt): array
    {
        $quiz = $attempt->quiz()->with('questions')->first();
        if (!$quiz) {
            return [];
        }

        return [
            'id'               => $quiz->id,
            'title'            => $quiz->title,
            'description'      => $quiz->description,
            'timeLimitMinutes' => $quiz->time_limit_minutes,
            'questions'        => $quiz->questions->map(fn (QuizQuestion $q) => [
                'id'           => $q->id,
                'sequence'     => $q->sequence,
                'prompt'       => $q->prompt,
                'questionType' => $q->question_type,
                'options'      => $q->options,
                'points'       => $q->points,
                // NB: correct_answer intentionally omitted — never sent to the
                // candidate; grading happens server-side on submit.
            ])->all(),
        ];
    }
}
