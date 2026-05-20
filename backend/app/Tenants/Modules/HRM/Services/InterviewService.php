<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Application;
use App\Models\Tenant\Interview;
use App\Models\Tenant\InterviewFeedback;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InterviewService
{
    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = Interview::query()->with(['application', 'interviewers']);

        if (!empty($filters['applicationId'])) {
            $query->where('application_id', $filters['applicationId']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('scheduled_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('scheduled_at', '<=', $filters['to']);
        }

        $query->orderBy('scheduled_at');

        return $query;
    }

    /**
     * Schedule an interview and (optionally) move the application into the
     * `interview` stage. Interviewer IDs are written into the M:N pivot
     * inside the same transaction.
     */
    public function schedule(array $data, array $interviewerIds = []): Interview
    {
        $data['status'] ??= $this->statuses->initialFor('hrm.interview');

        return DB::transaction(function () use ($data, $interviewerIds) {
            /** @var Interview $interview */
            $interview = Interview::create($data);

            if (!empty($interviewerIds)) {
                $interview->interviewers()->sync($interviewerIds);
            }

            // Best-effort promotion of the application. If the tenant has
            // customised hrm.application and the move isn't allowed from the
            // current stage, swallow the error — HR still has the interview
            // record; they'll advance the application manually.
            $application = $interview->application;
            if ($application) {
                try {
                    $this->statuses->validateTransition('hrm.application', $application->status, 'interview');
                    $application->update(['status' => 'interview']);
                } catch (DomainException) {
                    // intentional: do not block scheduling
                }
            }

            return $interview->fresh(['application', 'interviewers']);
        });
    }

    public function reschedule(Interview $interview, array $data): Interview
    {
        if ($interview->status !== 'scheduled') {
            throw new DomainException('Only scheduled interviews can be rescheduled.');
        }

        $interview->update($data);
        return $interview->fresh(['application', 'interviewers']);
    }

    public function cancel(Interview $interview, ?string $reason = null): Interview
    {
        $this->statuses->validateTransition('hrm.interview', $interview->status, 'cancelled');

        $interview->update([
            'status' => 'cancelled',
            'notes'  => $reason ? trim(($interview->notes ?? '') . "\nCancelled: " . $reason) : $interview->notes,
        ]);

        return $interview->fresh();
    }

    public function complete(Interview $interview): Interview
    {
        $this->statuses->validateTransition('hrm.interview', $interview->status, 'completed');

        $interview->update(['status' => 'completed']);
        return $interview->fresh(['feedback']);
    }

    /**
     * Record feedback from an interviewer. Each interviewer can submit at
     * most one feedback row per interview (enforced by the unique pivot).
     */
    public function submitFeedback(Interview $interview, string $interviewerId, array $data): InterviewFeedback
    {
        if (!in_array($interview->status, ['scheduled', 'completed'], true)) {
            throw new DomainException('Feedback can only be submitted on scheduled or completed interviews.');
        }

        $data = array_merge($data, [
            'interview_id'   => $interview->id,
            'interviewer_id' => $interviewerId,
            'submitted_at'   => $data['submitted_at'] ?? now(),
        ]);

        return DB::transaction(function () use ($data) {
            return InterviewFeedback::updateOrCreate(
                [
                    'interview_id'   => $data['interview_id'],
                    'interviewer_id' => $data['interviewer_id'],
                ],
                $data
            );
        });
    }

    /**
     * Aggregate scorecard for an interview: average rating + recommendation
     * tally. Used by HR when promoting the application past `interview`.
     *
     * @return array{averageRating: ?float, recommendations: array<string,int>, submittedCount: int, pendingCount: int}
     */
    public function scorecardFor(Interview $interview): array
    {
        $interview->loadMissing(['feedback', 'interviewers']);

        $recommendations = $interview->feedback
            ->whereNotNull('recommendation')
            ->groupBy('recommendation')
            ->map(fn ($rows) => $rows->count())
            ->all();

        return [
            'averageRating'   => $interview->averageRating(),
            'recommendations' => $recommendations,
            'submittedCount'  => $interview->feedback->count(),
            'pendingCount'    => max(0, $interview->interviewers->count() - $interview->feedback->count()),
        ];
    }
}
