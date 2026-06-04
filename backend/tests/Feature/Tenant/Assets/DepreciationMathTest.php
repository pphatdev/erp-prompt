<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Assets;

use App\Models\Tenant\Asset;
use App\Tenants\Modules\Assets\Services\DepreciationService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 7 P0 - DepreciationService math accuracy.
 *
 * Locks the canonical depreciation formulas + the NBV-floor invariant so
 * any change to the rounding or the rate calculation must break this
 * fixture deliberately.
 *
 *   straight_line:  amount = (cost - salvage) / life_months
 *   declining_balance (factor=2):  amount = NBV * (2 / life_months)
 *   sum_of_years_digits:  amount = (cost - salvage) * (L - N + 1) / (L*(L+1)/2)
 *
 *   Invariant: every method caps the amount at (NBV - salvage) so the
 *   resulting NBV can never drop below salvage_value.
 */
class DepreciationMathTest extends TenantTestCase
{
    private DepreciationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DepreciationService::class);
    }

    // ---------- Straight Line ----------------------------------------

    public function test_straight_line_first_period_amount(): void
    {
        // Cost 12000, salvage 0, life 36 months -> 333.33/month.
        $asset = $this->makeAsset(12000, 0, 36, 'straight_line', 0);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(333.33, $calc->amount);
        $this->assertSame('straight_line', $calc->method);
        $this->assertSame(333.33, $calc->accumulatedAfter);
        $this->assertSame(11666.67, $calc->nbvAfter);
    }

    public function test_straight_line_respects_salvage_floor(): void
    {
        // Cost 12000, salvage 1000, life 36 months -> 305.56/month.
        // accumulated already 10500, remaining depreciable = (12000 - 1000) - 10500 = 500.
        $asset = $this->makeAsset(12000, 1000, 36, 'straight_line', 10500);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        // Raw would be 305.56 but the salvage floor caps it at the
        // remaining 500.00.
        $this->assertSame(500.00, $calc->amount);
        $this->assertSame(11000.00, $calc->accumulatedAfter);
        $this->assertSame(1000.00, $calc->nbvAfter,
            'NBV must equal salvage exactly after the final cap-cap.');
    }

    public function test_already_fully_depreciated_returns_zero(): void
    {
        // NBV already at salvage -> nothing left to depreciate.
        $asset = $this->makeAsset(5000, 500, 24, 'straight_line', 4500);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(0.0, $calc->amount);
        $this->assertSame(500.00, $calc->nbvAfter);
    }

    // ---------- Declining Balance (default factor 2 = DDB) -----------

    public function test_declining_balance_first_period_doubles_straight_line_rate(): void
    {
        // Cost 10000, salvage 1000, life 60 months. NBV = 10000.
        // DDB monthly rate = 2 / 60 -> amount = 10000 * (2/60) = 333.33.
        $asset = $this->makeAsset(10000, 1000, 60, 'declining_balance', 0);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(333.33, $calc->amount);
        $this->assertSame(333.33, $calc->accumulatedAfter);
        $this->assertSame(9666.67, $calc->nbvAfter);
    }

    public function test_declining_balance_caps_at_salvage(): void
    {
        // NBV 1100, salvage 1000 -> remaining depreciable 100.
        // DDB raw: 1100 * 2/24 = 91.67. Under cap (100), so amount = 91.67.
        // But the NEXT period would compute 1008.33 * 2/24 = 84.03 > 8.33 remaining
        // so cap kicks in. We test the cap directly.
        $asset = $this->makeAsset(2000, 1000, 24, 'declining_balance', 950);
        // accumulated 950 -> NBV 1050, remaining 50.

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(50.00, $calc->amount,
            'DDB must be capped so NBV does not drop below salvage.');
        $this->assertSame(1000.00, $calc->nbvAfter);
    }

    // ---------- Sum of Years' Digits ---------------------------------

    public function test_sum_of_years_first_period(): void
    {
        // Life 6, denominator = 6*7/2 = 21.
        // Period 1 fraction = 6/21. Cost - salvage = 4200.
        // Amount = 4200 * 6/21 = 1200.
        $asset = $this->makeAsset(5000, 800, 6, 'sum_of_years_digits', 0);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(1200.00, $calc->amount);
        $this->assertSame(1200.00, $calc->accumulatedAfter);
        $this->assertSame(3800.00, $calc->nbvAfter);
    }

    public function test_sum_of_years_caps_to_salvage_floor(): void
    {
        // Cost 5000, salvage 4500, life 6, accumulated 400 -> NBV 4600, remaining 100.
        // Raw SYD fraction 6/21 -> 500 * 6/21 = 142.86 > 100, so cap to 100.
        $asset = $this->makeAsset(5000, 4500, 6, 'sum_of_years_digits', 400);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        $this->assertSame(100.00, $calc->amount);
        $this->assertSame(500.00, $calc->accumulatedAfter);
        $this->assertSame(4500.00, $calc->nbvAfter);
    }

    // ---------- Helper -----------------------------------------------

    private function makeAsset(
        float $cost,
        float $salvage,
        int $months,
        string $method,
        float $accumulated,
    ): Asset {
        return Asset::create([
            'asset_code'              => 'AST-' . uniqid(),
            'name'                    => 'Math fixture',
            'purchase_price'          => $cost,
            'salvage_value'           => $salvage,
            'useful_life_months'      => $months,
            'depreciation_method'     => $method,
            'accumulated_depreciation' => $accumulated,
            'status'                  => 'active',
        ]);
    }
}
