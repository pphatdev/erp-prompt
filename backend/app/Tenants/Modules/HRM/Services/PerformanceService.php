<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Appraisal;
use App\Models\Tenant\ApprovalWorkflow;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly ApprovalService $approvals,
    ) {
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
