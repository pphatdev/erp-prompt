<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Assets;

use App\Models\Tenant\Asset;
use App\Models\Tenant\DepreciationLog;
use App\Tenants\Modules\Assets\Services\DepreciationService;
use App\Tenants\Modules\FMS\Services\FmsIntegrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\Feature\TenantTestCase;

/**
 * Phase 7 P1 - FMS rollback + Auditable trail.
 *
 * Two concerns covered:
 *  1. When the FMS journal posting throws (e.g. locked fiscal period),
 *     `DepreciationService::runDepreciationForAsset` must roll the asset's
 *     accumulated_depreciation back to its pre-run value AND not leave a
 *     DepreciationLog row behind.
 *  2. The `Auditable` trait fires `Log::info('Audit Log: <action>', ...)`
 *     for asset create, asset update post-depreciation, and the
 *     DepreciationLog create row.
 */
class AssetsRollbackAndAuditTest extends TenantTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fms_failure_rolls_back_asset_and_depreciation_log(): void
    {
        // Bind a stubbed FMS service that throws — exercises the rollback path.
        $this->app->bind(FmsIntegrationService::class, function () {
            $stub = Mockery::mock(FmsIntegrationService::class);
            $stub->shouldReceive('postDepreciationJournal')
                ->andThrow(new RuntimeException('Fiscal period locked.'));
            return $stub;
        });

        $asset = Asset::create([
            'asset_code'             => 'AST-RB-001',
            'name'                   => 'Rollback fixture',
            'purchase_price'         => 1200.00,
            'salvage_value'          => 0.00,
            'useful_life_months'     => 12,
            'depreciation_method'    => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'status'                 => 'active',
        ]);

        $service = app(DepreciationService::class);

        $threw = false;
        try {
            $service->runDepreciationForAsset($asset, Carbon::create(2026, 1, 31));
        } catch (RuntimeException $e) {
            $threw = $e->getMessage() === 'Fiscal period locked.';
        }
        $this->assertTrue($threw, 'FMS exception must propagate.');

        $asset->refresh();
        $this->assertSame(0.00, (float) $asset->accumulated_depreciation,
            'Asset accumulated_depreciation must roll back to 0 on FMS failure.');
        $this->assertSame(0, DepreciationLog::where('asset_id', $asset->id)->count(),
            'No DepreciationLog row may survive a failed FMS post.');
    }

    public function test_auditable_trait_logs_asset_create_update_and_depreciation_log_create(): void
    {
        Log::spy();

        $asset = Asset::create([
            'asset_code'             => 'AST-AUD-001',
            'name'                   => 'Audit fixture',
            'purchase_price'         => 6000.00,
            'salvage_value'          => 600.00,
            'useful_life_months'     => 12,
            'depreciation_method'    => 'straight_line',
            'accumulated_depreciation' => 0.00,
            'status'                 => 'active',
        ]);

        $this->assertAuditLogged('create', Asset::class, $asset->id);

        $log = app(DepreciationService::class)
            ->runDepreciationForAsset($asset, Carbon::create(2026, 1, 31));

        $this->assertNotNull($log, 'Service must return a DepreciationLog on success.');
        // Asset.accumulated_depreciation was updated -> audit fires.
        $this->assertAuditLogged('update', Asset::class, $asset->id);
        // DepreciationLog row was inserted -> audit fires.
        $this->assertAuditLogged('create', DepreciationLog::class, $log->id);
    }

    private function assertAuditLogged(string $action, string $modelClass, string $modelId): void
    {
        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context = null) use ($action, $modelClass, $modelId) {
                if ($message !== "Audit Log: {$action}") return false;
                if (!is_array($context)) return false;
                return ($context['model'] ?? null) === $modelClass
                    && ($context['id'] ?? null) === $modelId;
            })->atLeast()->once();
    }
}
