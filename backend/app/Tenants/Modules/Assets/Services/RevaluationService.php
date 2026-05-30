<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetRevaluationLog;
use App\Tenants\Modules\FMS\Services\FmsIntegrationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RevaluationService
{
    public function __construct(private readonly FmsIntegrationService $fms)
    {
    }

    /**
     * Log a professional appraisal and adjust the asset's book value to match.
     *
     * Surplus (appraisal > NBV): Debit asset value, Credit revaluation reserve.
     * Loss     (appraisal < NBV): Debit revaluation loss, Credit asset value.
     *
     * The adjustment is applied via `accumulated_depreciation` so the asset's
     * original `purchase_price` history is preserved.
     */
    public function revalue(
        Asset $asset,
        float $appraisalValue,
        ?string $appraiser = null,
        ?string $notes = null,
        ?CarbonInterface $appraisalDate = null,
    ): AssetRevaluationLog {
        $previousNbv = (float) $asset->net_book_value;
        $adjustment = round($appraisalValue - $previousNbv, 2);
        $type = $adjustment >= 0 ? 'surplus' : 'loss';
        $date = $appraisalDate ?? Carbon::now();

        return DB::transaction(function () use ($asset, $appraisalValue, $previousNbv, $adjustment, $type, $appraiser, $notes, $date) {
            $log = AssetRevaluationLog::create([
                'asset_id'          => $asset->id,
                'appraisal_date'    => $date->toDateString(),
                'previous_value'    => $previousNbv,
                'appraisal_value'   => $appraisalValue,
                'adjustment_amount' => $adjustment,
                'adjustment_type'   => $type,
                'appraiser'         => $appraiser,
                'notes'             => $notes,
            ]);

            // Apply the adjustment by shifting accumulated_depreciation so that
            // NBV (= purchase_price - accumulated_depreciation) lands at the
            // new appraisal_value. A positive surplus decreases accumulated;
            // a loss increases it.
            $cost = (float) $asset->purchase_price;
            $newAccumulated = round($cost - $appraisalValue, 2);
            $asset->update(['accumulated_depreciation' => $newAccumulated]);

            $journal = $this->fms->postRevaluationJournal($asset, $log);

            $log->update(['journal_entry_id' => $journal->id]);

            return $log->refresh();
        });
    }
}
