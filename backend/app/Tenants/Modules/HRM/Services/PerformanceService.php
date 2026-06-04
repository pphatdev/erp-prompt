<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Appraisal;
use App\Models\Tenant\AppraisalPeerFeedback;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceService
{
    /**
     * Registry defaults — used as the fallback when the tenant either
     * hasn't customised the weights or has set values that don't validate
     * (non-numeric / out-of-range / don't sum to 100).
     */
    private const DEFAULT_SELF_WEIGHT = 20;
    private const DEFAULT_MANAGER_WEIGHT = 80;

    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly ApprovalService $approvals,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * Phase 9 hook: tenant-configurable self / manager evaluation weights.
     *
     * Reads `hrm.appraisal.self_evaluation_weight` and
     * `hrm.appraisal.manager_evaluation_weight` (both percentages). When the
     * pair fails validation (non-numeric, out of 0..100, or sum != 100),
     * logs a warning and falls back to the registry defaults [20, 80] so
     * the appraisal pipeline keeps running instead of throwing inside a
     * close-flow.
     *
     * @return array{self:int, manager:int}
     */
    public function appraisalWeights(): array
    {
        $rawSelf    = $this->settings->get('hrm.appraisal.self_evaluation_weight');
        $rawManager = $this->settings->get('hrm.appraisal.manager_evaluation_weight');

        if (!is_numeric($rawSelf) || !is_numeric($rawManager)) {
            return ['self' => self::DEFAULT_SELF_WEIGHT, 'manager' => self::DEFAULT_MANAGER_WEIGHT];
        }

        $self    = (int) $rawSelf;
        $manager = (int) $rawManager;

        if ($self < 0 || $self > 100 || $manager < 0 || $manager > 100 || ($self + $manager) !== 100) {
            Log::warning('Appraisal weights mis-configured — falling back to defaults [20, 80].', [
                'self_evaluation_weight'    => $rawSelf,
                'manager_evaluation_weight' => $rawManager,
            ]);
            return ['self' => self::DEFAULT_SELF_WEIGHT, 'manager' => self::DEFAULT_MANAGER_WEIGHT];
        }

        return ['self' => $self, 'manager' => $manager];
    }

    /**
     * Phase 9 hook: weighted final score = self×self% + manager×manager%.
     * Returns the rounded-to-2dp blend. Pure function — does not mutate
     * the appraisal.
     */
    public function computeFinalScore(float $selfScore, float $managerScore): float
    {
        $w = $this->appraisalWeights();
        $weighted = ($selfScore * $w['self'] + $managerScore * $w['manager']) / 100;
        return round($weighted, 2);
    }

    /**
     * Phase 4 (360-degree feedback): blend in the peer-feedback average when
     * the tenant has set the optional `hrm.appraisal.peer_evaluation_weight`
     * setting. The peer weight is treated as percentage points reallocated
     * from the manager share so the total still sums to 100. Falls back to
     * the 2-component computeFinalScore when no peer rating is provided or
     * the peer weight is unset.
     */
    public function computeFinalScoreWithPeer(
        float $selfScore,
        float $managerScore,
        ?float $peerScore,
    ): float {
        $rawPeerWeight = $this->settings->get('hrm.appraisal.peer_evaluation_weight');
        $peerWeight = is_numeric($rawPeerWeight) ? (int) $rawPeerWeight : 0;

        if ($peerScore === null || $peerWeight <= 0 || $peerWeight > 100) {
            return $this->computeFinalScore($selfScore, $managerScore);
        }

        $base = $this->appraisalWeights();
        // Reallocate peer share from the manager column so the trio still
        // sums to 100. When manager < peer, clamp to zero so we never go
        // negative; the unallocated remainder stays implicit at zero.
        $managerWeight = max(0, $base['manager'] - $peerWeight);
        $selfWeight    = $base['self'];

        $weighted = ($selfScore * $selfWeight + $managerScore * $managerWeight + $peerScore * $peerWeight) / 100;
        return round($weighted, 2);
    }

    /**
     * Phase 9 hook: convenience wrapper — computes the weighted score from
     * the provided components and writes it onto `overall_rating`. When the
     * appraisal has submitted peer feedback AND
     * `hrm.appraisal.peer_evaluation_weight` is set, the peer average blends
     * in automatically.
     */
    public function applyWeightedRating(Appraisal $appraisal, float $selfScore, float $managerScore): float
    {
        $peerScore = $appraisal->averagePeerRating(); // null when none submitted
        $final = $this->computeFinalScoreWithPeer($selfScore, $managerScore, $peerScore);
        $appraisal->update(['overall_rating' => $final]);
        return $final;
    }

    // ------------------------------------------------------------------
    // Phase 4: 360-degree peer feedback
    // ------------------------------------------------------------------

    /**
     * Invite a peer to review an appraisal. Idempotent — re-inviting the
     * same reviewer returns the existing row (so the UI can re-send the
     * notification without surfacing a "duplicate" error).
     */
    public function invitePeerReviewer(Appraisal $appraisal, Employee $reviewer): AppraisalPeerFeedback
    {
        if ($appraisal->status === 'closed') {
            throw new DomainException('Cannot invite peer reviewers on a closed appraisal.');
        }
        if ($reviewer->id === $appraisal->employee_id) {
            throw new DomainException('An employee cannot peer-review their own appraisal.');
        }

        return AppraisalPeerFeedback::query()->firstOrCreate(
            [
                'appraisal_id' => $appraisal->id,
                'reviewer_id'  => $reviewer->id,
            ],
            [
                'status'     => AppraisalPeerFeedback::STATUS_INVITED,
                'invited_at' => now(),
            ],
        );
    }

    /**
     * Submit / update a peer reviewer's feedback. Upserts so a reviewer
     * can revise their submission while the appraisal is still open.
     * Caller is the reviewer (route is gated by `PeerFeedbackPolicy`).
     */
    public function submitPeerFeedback(
        Appraisal $appraisal,
        Employee $reviewer,
        array $payload,
    ): AppraisalPeerFeedback {
        if (in_array($appraisal->status, ['closed', 'reviewed'], true)) {
            throw new DomainException('Peer feedback is no longer accepted on closed/reviewed appraisals.');
        }
        if ($reviewer->id === $appraisal->employee_id) {
            throw new DomainException('An employee cannot peer-review their own appraisal.');
        }

        $row = $this->invitePeerReviewer($appraisal, $reviewer);
        $row->update(array_merge($payload, [
            'status'       => AppraisalPeerFeedback::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]));

        return $row->fresh();
    }

    /**
     * Roll up the peer feedback for a single appraisal.
     *
     * @return array{average: ?float, submittedCount: int, pendingCount: int, declinedCount: int}
     */
    public function aggregatePeerFeedback(Appraisal $appraisal): array
    {
        $rows = $appraisal->peerFeedbacks()->get();

        $submitted = $rows->where('status', AppraisalPeerFeedback::STATUS_SUBMITTED);
        $pending   = $rows->where('status', AppraisalPeerFeedback::STATUS_INVITED);
        $declined  = $rows->where('status', AppraisalPeerFeedback::STATUS_DECLINED);

        $ratings = $submitted->pluck('rating')->filter(fn ($v) => $v !== null);

        return [
            'average'        => $ratings->isEmpty() ? null : round((float) $ratings->avg(), 2),
            'submittedCount' => $submitted->count(),
            'pendingCount'   => $pending->count(),
            'declinedCount'  => $declined->count(),
        ];
    }


    /**
     * Appraisal listing. Filters: employeeId, reviewerId, cycle, status.
     */
    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = Appraisal::query()->with(['employee', 'reviewer']);

        if (!empty($filters['employeeId'])) {
            $query->where('employee_id', $filters['employeeId']);
        }
        if (!empty($filters['reviewerId'])) {
            $query->where('reviewer_id', $filters['reviewerId']);
        }
        if (!empty($filters['cycle'])) {
            $query->where('cycle', $filters['cycle']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('period_end', 'desc');

        return $query;
    }

    public function createAppraisal(array $data): Appraisal
    {
        $data['status'] = $this->statuses->initialFor('hrm.appraisal');

        return DB::transaction(function () use ($data) {
            $appraisal = Appraisal::create($data);

            $workflow = $this->appraisalWorkflow();
            $requesterId = Auth::id() ?? $appraisal->employee?->user_id;

            if ($workflow && $requesterId) {
                $this->approvals->submitRequest(
                    workflowId: $workflow->id,
                    requesterId: (string) $requesterId,
                    requestableType: Appraisal::class,
                    requestableId: (string) $appraisal->id,
                );
            }

            return $appraisal;
        });
    }

    private function appraisalWorkflow(): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('module', 'hrm')
            ->where('type', 'appraisal')
            ->orderBy('created_at')
            ->first();
    }

    public function updateAppraisal(Appraisal $appraisal, array $data): Appraisal
    {
        if ($appraisal->status === 'closed') {
            throw new DomainException('Closed appraisals are immutable.');
        }

        return DB::transaction(function () use ($appraisal, $data) {
            $appraisal->update($data);
            return $appraisal->fresh(['employee', 'reviewer']);
        });
    }

    public function submit(Appraisal $appraisal): Appraisal
    {
        $this->assertTransition($appraisal, 'submitted');

        $appraisal->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $appraisal->fresh(['employee', 'reviewer']);
    }

    public function review(Appraisal $appraisal, array $reviewerData): Appraisal
    {
        $this->assertTransition($appraisal, 'reviewed');

        return DB::transaction(function () use ($appraisal, $reviewerData) {
            $appraisal->update(array_merge($reviewerData, [
                'status' => 'reviewed',
                'reviewed_at' => now(),
            ]));

            return $appraisal->fresh(['employee', 'reviewer']);
        });
    }

    public function close(Appraisal $appraisal): Appraisal
    {
        $this->assertTransition($appraisal, 'closed');

        $appraisal->update(['status' => 'closed']);

        return $appraisal->fresh(['employee', 'reviewer']);
    }

    private function assertTransition(Appraisal $appraisal, string $toStatus): void
    {
        $this->statuses->validateTransition('hrm.appraisal', $appraisal->status, $toStatus);
    }
}
