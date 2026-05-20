<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Application;
use App\Models\Tenant\Quiz;
use App\Models\Tenant\QuizAttempt;
use App\Models\Tenant\QuizQuestion;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizService
{
    /**
     * Result returned to the admin when assigning a quiz — the raw token is
     * returned ONCE here. After that the only persisted form is its SHA-256
     * hash on the attempt row.
     */
    public const STATUS_ASSESSMENT          = 'assessment';
    public const STATUS_ASSESSMENT_COMPLETE = 'assessment_completed';

    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    public function createQuiz(array $data): Quiz
    {
        return DB::transaction(function () use ($data) {
            $questions = $data['questions'] ?? [];
            unset($data['questions']);

            $data['status'] ??= 'draft';
            $quiz = Quiz::create($data);

            foreach ($questions as $i => $q) {
                $this->createQuestion($quiz, array_merge($q, ['sequence' => $q['sequence'] ?? $i + 1]));
            }

            return $quiz->load('questions');
        });
    }

    public function updateQuiz(Quiz $quiz, array $data): Quiz
    {
        $quiz->update($data);
        return $quiz->fresh('questions');
    }

    public function createQuestion(Quiz $quiz, array $data): QuizQuestion
    {
        if (isset($data['correct_answer']) && !is_string($data['correct_answer'])) {
            // Persist the canonical JSON shape so the encrypted cast always has
            // a single representation.
            $data['correct_answer'] = json_encode(array_values((array) $data['correct_answer']));
        }

        $data['quiz_id'] = $quiz->id;

        return QuizQuestion::create($data);
    }

    /**
     * Assign a quiz to a recruitment Application. Generates a magic-link token,
     * stores only its hash, advances the application to `assessment`, and
     * returns the raw token so the caller can build the candidate URL.
     *
     * @return array{attempt: QuizAttempt, token: string}
     */
    public function assignToApplication(Quiz $quiz, Application $application, int $expiresInHours = 72): array
    {
        if ($quiz->status !== 'published') {
            throw new DomainException('Quiz must be published before assignment.');
        }

        return DB::transaction(function () use ($quiz, $application, $expiresInHours) {
            $token = $this->generateToken();

            $attempt = QuizAttempt::create([
                'quiz_id'           => $quiz->id,
                'application_id'    => $application->id,
                'candidate_email'   => $application->applicant_email,
                'candidate_name'    => $application->applicant_name,
                'secure_token_hash' => $this->hashToken($token),
                'invited_at'        => now(),
                'expires_at'        => now()->addHours($expiresInHours),
                'status'            => 'invited',
            ]);

            // Move the application into the assessment stage. If the tenant
            // hasn't customised hrm.application to include this status,
            // validateTransition fails fast and the assignment rolls back.
            $this->statuses->validateTransition('hrm.application', $application->status, self::STATUS_ASSESSMENT);
            $application->update(['status' => self::STATUS_ASSESSMENT]);

            return ['attempt' => $attempt, 'token' => $token];
        });
    }

    /**
     * Token lookup for the candidate-facing endpoints. Returns null when the
     * token doesn't match any attempt — callers translate that into a 404.
     */
    public function findAttemptByToken(string $token): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('secure_token_hash', $this->hashToken($token))
            ->first();
    }

    public function startAttempt(QuizAttempt $attempt): QuizAttempt
    {
        $this->assertUsable($attempt);

        if ($attempt->status === 'in_progress') {
            return $attempt; // idempotent — client may retry on flaky network
        }

        $attempt->update([
            'status'     => 'in_progress',
            'started_at' => $attempt->started_at ?? now(),
        ]);

        return $attempt->fresh();
    }

    /**
     * Grade the submitted answers, persist score/passed/answers, mark the
     * attempt completed, and flip the linked Application to
     * `assessment_completed`.
     */
    public function submitAttempt(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        $this->assertUsable($attempt);

        return DB::transaction(function () use ($attempt, $answers) {
            $attempt->load('quiz.questions');

            $graded = $this->gradeAttempt($attempt, $answers);

            $attempt->update([
                'status'       => 'completed',
                'submitted_at' => now(),
                'answers'      => $answers,
                'score'        => $graded['score'],
                'passed'       => $graded['passed'],
            ]);

            if ($attempt->application) {
                $app = $attempt->application;
                try {
                    $this->statuses->validateTransition('hrm.application', $app->status, self::STATUS_ASSESSMENT_COMPLETE);
                    $app->update(['status' => self::STATUS_ASSESSMENT_COMPLETE]);
                } catch (DomainException) {
                    // Tenant may have removed the assessment_completed status
                    // from their workflow — completion is still recorded on
                    // the attempt; HR moves the application manually.
                }
            }

            return $attempt->fresh(['application', 'quiz']);
        });
    }

    /**
     * Plain-text correct answer for a question — service-layer accessor so
     * Resources can leak it to admins only.
     */
    public function correctAnswerFor(QuizQuestion $question): mixed
    {
        $raw = $question->correct_answer;
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return $decoded ?? $raw;
    }

    private function gradeAttempt(QuizAttempt $attempt, array $submittedAnswers): array
    {
        $totalPoints = 0;
        $awarded = 0;

        foreach ($attempt->quiz->questions as $question) {
            $totalPoints += $question->points;

            $submitted = $submittedAnswers[$question->id] ?? null;
            $correct   = $this->correctAnswerFor($question);

            if ($this->isCorrect($question->question_type, $submitted, $correct)) {
                $awarded += $question->points;
            }
        }

        $score  = $totalPoints > 0 ? round(($awarded / $totalPoints) * 100, 2) : 0.0;
        $passed = $attempt->quiz->pass_score !== null
            ? $score >= (float) $attempt->quiz->pass_score
            : null;

        return ['score' => $score, 'passed' => $passed];
    }

    private function isCorrect(string $type, mixed $submitted, mixed $correct): bool
    {
        if ($submitted === null || $correct === null) {
            return false;
        }

        if ($type === 'single_choice' || $type === 'multiple_choice') {
            $s = array_map('strval', (array) $submitted);
            $c = array_map('strval', (array) $correct);
            sort($s);
            sort($c);
            return $s === $c;
        }

        if ($type === 'short_text') {
            return strcasecmp(trim((string) $submitted), trim((string) $correct)) === 0;
        }

        return false;
    }

    private function assertUsable(QuizAttempt $attempt): void
    {
        if (in_array($attempt->status, ['completed', 'expired', 'abandoned'], true)) {
            throw new DomainException('Quiz attempt is no longer active.');
        }

        if ($attempt->expires_at && CarbonImmutable::now()->greaterThan($attempt->expires_at)) {
            $attempt->update(['status' => 'expired']);
            throw new DomainException('Quiz attempt has expired.');
        }
    }

    /**
     * 64-char URL-safe token (256 bits of entropy from random_bytes(32)).
     */
    private function generateToken(): string
    {
        return Str::random(64);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
