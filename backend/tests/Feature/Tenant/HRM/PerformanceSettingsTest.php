<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Appraisal;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Services\PerformanceService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 9 Item 5 — PerformanceService consults hrm.appraisal.* weights.
 *
 *  appraisalWeights():
 *    - Defaults to [self=20, manager=80] when nothing's set.
 *    - Honors a valid pair that sums to 100.
 *    - Falls back to defaults when the pair doesn't sum (mis-config).
 *    - Falls back to defaults on non-numeric values.
 *    - Falls back to defaults on out-of-range values (negative / >100).
 *
 *  computeFinalScore():
 *    - 20/80 default → 4 * 0.2 + 5 * 0.8 = 4.80.
 *    - 50/50 split   → 4 * 0.5 + 5 * 0.5 = 4.50.
 *    - 100/0 weight  → entirely the self score.
 *    - Rounds to 2 decimals.
 *
 *  applyWeightedRating():
 *    - Writes the computed score into `overall_rating` on the model.
 */
class PerformanceSettingsTest extends TenantTestCase
{
    private PerformanceService $service;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = app(PerformanceService::class);
        $this->settings = app(SettingService::class);
    }

    public function test_appraisal_weights_default_to_20_80(): void
    {
        $w = $this->service->appraisalWeights();
        $this->assertSame(['self' => 20, 'manager' => 80], $w);
    }

    public function test_appraisal_weights_honor_valid_pair(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', 30, 'integer');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 70, 'integer');

        $w = $this->service->appraisalWeights();
        $this->assertSame(['self' => 30, 'manager' => 70], $w);
    }

    public function test_appraisal_weights_fall_back_when_sum_is_not_100(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', 40, 'integer');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 50, 'integer');

        $w = $this->service->appraisalWeights();
        $this->assertSame(['self' => 20, 'manager' => 80], $w);
    }

    public function test_appraisal_weights_fall_back_on_non_numeric(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', 'half', 'string');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 50, 'integer');

        $w = $this->service->appraisalWeights();
        $this->assertSame(['self' => 20, 'manager' => 80], $w);
    }

    public function test_appraisal_weights_fall_back_on_out_of_range(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', -10, 'integer');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 110, 'integer');

        $w = $this->service->appraisalWeights();
        $this->assertSame(['self' => 20, 'manager' => 80], $w);
    }

    public function test_compute_final_score_uses_default_20_80(): void
    {
        // 4 * 0.2 + 5 * 0.8 = 4.80
        $score = $this->service->computeFinalScore(4.0, 5.0);
        $this->assertSame(4.80, $score);
    }

    public function test_compute_final_score_uses_50_50_when_configured(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', 50, 'integer');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 50, 'integer');

        $score = $this->service->computeFinalScore(4.0, 5.0);
        $this->assertSame(4.5, $score);
    }

    public function test_compute_final_score_uses_100_0_when_configured(): void
    {
        $this->settings->set('hrm.appraisal.self_evaluation_weight', 100, 'integer');
        $this->settings->set('hrm.appraisal.manager_evaluation_weight', 0, 'integer');

        $score = $this->service->computeFinalScore(3.7, 4.9);
        $this->assertSame(3.7, $score);
    }

    public function test_apply_weighted_rating_persists_to_overall_rating(): void
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-AP-TEST',
            'first_name'  => 'Apr',
            'last_name'   => 'Aiser',
            'email'       => 'apr.aiser@example.test',
            'hired_at'    => '2025-01-01',
            'status'      => 'active',
        ]);

        $appraisal = Appraisal::create([
            'employee_id'  => $employee->id,
            'cycle'        => 'H1 2026',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-06-30',
            'status'       => 'draft',
        ]);

        $score = $this->service->applyWeightedRating($appraisal, 4.0, 5.0);
        $this->assertSame(4.80, $score);

        $appraisal->refresh();
        $this->assertSame('4.80', $appraisal->overall_rating);
    }
}
