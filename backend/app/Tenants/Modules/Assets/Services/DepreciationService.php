<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\DepreciationLog;
use App\Tenants\Modules\FMS\Services\FmsIntegrationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mathematical depreciation engine. Three methods supported per the spec:
 *   - straight_line:        Cost - Salvage divided evenly across the life.
 *   - declining_balance:    Accelerated; reads `db_factor` (defaults to 2 == DDB).
 *   - sum_of_years_digits:  Accelerated, fraction-based on remaining life.
 *
 * Invariants (enforced in every method):
 *   1. NBV (Net Book Value) must never fall below `salvage_value`.
 *   2. Total accumulated depreciation must never exceed (purchase_price - salvage_value).
 *
 * When NBV - calculated_amount would breach the salvage floor, the amount is
 * capped at the remaining depreciable value.
 */
class DepreciationService
{
    public const METHOD_STRAIGHT_LINE      = 'straight_line';
    public const METHOD_DECLINING_BALANCE  = 'declining_balance';
    public const METHOD_SUM_OF_YEARS       = 'sum_of_years_digits';

    public function __construct(private readonly FmsIntegrationService $fms)
    {
    }

    /**
     * Pure computation — no DB writes, no FMS calls. Returned object exposes:
     *   - amount: float (capped at salvage threshold)
     *   - method: string
     *   - accumulatedAfter: float
     *   - nbvAfter: float
     */
    public function calculateNextMonthlyDepreciation(Asset $asset): object
    {
        $method = (string) ($asset->depreciation_method ?? self::METHOD_STRAIGHT_LINE);
        $cost = (float) $asset->purchase_price;
        $salvage = (float) $asset->salvage_value;
        $months = max(1, (int) $asset->useful_life_months);
        $accumulated = (float) $asset->accumulated_depreciation;
        $nbv = round($cost - $accumulated, 2);

        // Already fully depreciated — return zero.
        if ($nbv <= $salvage) {
            return $this->result(0.0, $method, $accumulated, $nbv);
        }

        $raw = match ($method) {
            self::METHOD_STRAIGHT_LINE     => $this->straightLineAmount($cost, $salvage, $months),
            self::METHOD_DECLINING_BALANCE => $this->decliningBalanceAmount($nbv, $months, $asset),
            self::METHOD_SUM_OF_YEARS      => $this->sumOfYearsAmount($asset, $cost, $salvage, $accumulated),
            default                        => throw new InvalidArgumentException("Unsupported depreciation method: {$method}"),
        };

        $remainingDepreciable = round($nbv - $salvage, 2);
        $amount = round(min($raw, $remainingDepreciable), 2);

        return $this->result(
            $amount,
            $method,
            round($accumulated + $amount, 2),
            round($nbv - $amount, 2),
        );
    }

    /**
     * Atomic: compute -> persist log -> bump asset -> post FMS journal.
     * Any failure (including FMS exceptions like a locked period) rolls back
     * the asset's accumulated_depreciation and the new log row.
     */
    public function runDepreciationForAsset(Asset $asset, ?CarbonInterface $periodDate = null): ?DepreciationLog
    {
        if ($asset->status !== 'active') {
            return null;
        }

        $calc = $this->calculateNextMonthlyDepreciation($asset);
        if ($calc->amount <= 0) {
            return null;
        }

        $period = $periodDate ?? Carbon::now()->endOfMonth();

        return DB::transaction(function () use ($asset, $calc, $period) {
            $log = DepreciationLog::create([
                'asset_id'                 => $asset->id,
                'period_date'              => $period->toDateString(),
                'depreciation_amount'      => $calc->amount,
                'accumulated_depreciation' => $calc->accumulatedAfter,
                'book_value'               => $calc->nbvAfter,
                'method'                   => $calc->method,
            ]);

            $asset->update([
                'accumulated_depreciation' => $calc->accumulatedAfter,
            ]);

            $journal = $this->fms->postDepreciationJournal($asset, $calc->amount, $period);

            $log->update(['journal_entry_id' => $journal->id]);

            return $log->refresh();
        });
    }

    private function straightLineAmount(float $cost, float $salvage, int $months): float
    {
        return round(($cost - $salvage) / $months, 2);
    }

    /**
     * Declining balance. `db_factor` defaults to 2 (Double-Declining). Set to
     * 1 for single declining. Monthly rate = factor / life_in_months.
     */
    private function decliningBalanceAmount(float $nbv, int $months, Asset $asset): float
    {
        $factor = (float) ($asset->getAttribute('db_factor') ?? 2.0);
        $monthlyRate = $factor / $months;

        return round($nbv * $monthlyRate, 2);
    }

    /**
     * Sum-of-the-Years'-Digits, monthly. For an asset with life L (months):
     *   SYD denominator = L * (L + 1) / 2
     *   Period N (1..L) fraction = (L - N + 1) / denominator
     *   Monthly depreciation = (cost - salvage) * fraction
     *
     * The period number is derived from how much of the life has elapsed —
     * computed from the count of prior posted logs.
     */
    private function sumOfYearsAmount(Asset $asset, float $cost, float $salvage, float $accumulated): float
    {
        $life = max(1, (int) $asset->useful_life_months);
        $denominator = ($life * ($life + 1)) / 2;

        $periodsElapsed = (int) $asset->depreciationLogs()->count();
        $periodN = min($periodsElapsed + 1, $life);

        $fraction = ($life - $periodN + 1) / $denominator;

        return round(($cost - $salvage) * $fraction, 2);
    }

    private function result(float $amount, string $method, float $accumulatedAfter, float $nbvAfter): object
    {
        return (object) [
            'amount'           => $amount,
            'method'           => $method,
            'accumulatedAfter' => $accumulatedAfter,
            'nbvAfter'         => $nbvAfter,
        ];
    }
}
