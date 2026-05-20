<?php

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\DepreciationLog;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    /**
     * Calculate and log depreciation for an asset for a specific period.
     */
    public function calculateDepreciation(Asset $asset, string $periodDate): ?DepreciationLog
    {
        if ($asset->status !== 'active') {
            return null;
        }

        // Extremely simplified Straight-line calculation for example purposes
        // Annual Depreciation = (Purchase Cost - Salvage Value) / Useful Life
        // Monthly = Annual / 12
        
        $annualDepreciation = ($asset->purchase_cost - $asset->salvage_value) / max(1, $asset->useful_life_years);
        $monthlyDepreciation = $annualDepreciation / 12;

        if ($asset->current_value <= $asset->salvage_value) {
            return null; // Fully depreciated
        }

        $depreciationAmount = min($monthlyDepreciation, $asset->current_value - $asset->salvage_value);
        $newBookValue = $asset->current_value - $depreciationAmount;
        
        // Retrieve last accumulated, or default to 0
        $lastLog = $asset->depreciationLogs()->orderBy('period_date', 'desc')->first();
        $accumulated = ($lastLog ? $lastLog->accumulated_depreciation : 0) + $depreciationAmount;

        return DB::transaction(function () use ($asset, $periodDate, $depreciationAmount, $accumulated, $newBookValue) {
            
            $log = DepreciationLog::create([
                'asset_id' => $asset->id,
                'period_date' => $periodDate,
                'depreciation_amount' => $depreciationAmount,
                'accumulated_depreciation' => $accumulated,
                'book_value' => $newBookValue,
            ]);

            $asset->update(['current_value' => $newBookValue]);

            // Placeholder: Call FMS AccountingService to post a journal entry here
            // $journal = app(AccountingService::class)->postEntry([...]);
            // $log->update(['journal_entry_id' => $journal->id]);

            return $log;
        });
    }
}
