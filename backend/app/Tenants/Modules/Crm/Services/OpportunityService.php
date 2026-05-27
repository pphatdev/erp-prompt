<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Services;

use App\Models\Tenant\Opportunity;
use App\Tenants\Modules\Crm\Events\LeadQualified;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OpportunityService
{
    public function buildQuery(): Builder
    {
        return Opportunity::query()->with(['customer', 'lead'])->orderByDesc('created_at');
    }

    public function create(array $data): Opportunity
    {
        return Opportunity::create($data)->load(['customer', 'lead']);
    }

    public function update(Opportunity $opp, array $data): Opportunity
    {
        $opp->update($data);
        return $opp->fresh(['customer', 'lead']);
    }

    public function updateStage(Opportunity $opp, string $stage, ?string $lossReason = null): Opportunity
    {
        if (in_array($opp->stage, Opportunity::TERMINAL_STAGES, true)) {
            throw new \DomainException("Opportunity is already {$opp->stage} and cannot be moved.");
        }

        if ($stage === Opportunity::STAGE_LOST && empty($lossReason)) {
            throw new \DomainException('A loss_reason is required when moving to lost.');
        }

        $fresh = DB::transaction(function () use ($opp, $stage, $lossReason) {
            $opp->update([
                'stage'       => $stage,
                'loss_reason' => $stage === Opportunity::STAGE_LOST ? $lossReason : $opp->loss_reason,
            ]);

            return $opp->fresh(['customer', 'lead', 'productSchedule']);
        });

        if ($stage === Opportunity::STAGE_WON) {
            LeadQualified::dispatch($fresh->lead, $fresh);
        }

        return $fresh;
    }
}
