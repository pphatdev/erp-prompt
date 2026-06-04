<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Assets;

use App\Models\Central\Tenant;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetDisposal;
use App\Models\Tenant\AssetRevaluationLog;
use App\Models\Tenant\DepreciationLog;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 P0 - Assets tenancy isolation.
 *
 * Boots two tenant DBs, writes the full asset lifecycle on Tenant A
 * (Asset + DepreciationLog + AssetRevaluationLog + AssetDisposal), then
 * asserts Tenant B's connection sees 0 of each via the `BelongsToTenant`
 * global scope and `find()` returns null. Standalone `TestCase` (not
 * `TenantTestCase`) so two `tenancy()->initialize()` calls can sit
 * side-by-side.
 */
class AssetsTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'ast-iso-a', 'handle' => 'ast-iso-a', 'name' => 'Assets Iso A']);
        $this->tenantB = Tenant::create(['id' => 'ast-iso-b', 'handle' => 'ast-iso-b', 'name' => 'Assets Iso B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_assets(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $asset = Asset::create([
            'asset_code'   => 'AST-A-001',
            'name'         => 'Tenant A laptop',
            'purchase_price' => 1500.00,
            'salvage_value'  => 100.00,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'status' => 'active',
            'condition' => 'Good',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Asset::count(),
            'Tenant B must not see Tenant A assets through the global scope.');
        $this->assertNull(Asset::find($asset->id),
            'Tenant B must not resolve Tenant A asset by UUID.');
        $this->assertSame(0, Asset::query()->where('asset_code', 'AST-A-001')->count(),
            'Tenant B must not match Tenant A asset code.');
    }

    public function test_tenant_b_cannot_see_tenant_a_depreciation_logs(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $asset = Asset::create([
            'asset_code' => 'AST-A-002',
            'name' => 'A vehicle',
            'purchase_price' => 30000.00,
            'salvage_value'  => 3000.00,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'status' => 'active',
        ]);
        $log = DepreciationLog::create([
            'asset_id' => $asset->id,
            'period_date' => '2026-01-31',
            'depreciation_amount' => 450.00,
            'accumulated_depreciation' => 450.00,
            'book_value' => 29550.00,
            'method' => 'straight_line',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, DepreciationLog::count(),
            'Tenant B must not enumerate Tenant A depreciation logs.');
        $this->assertNull(DepreciationLog::find($log->id));
    }

    public function test_tenant_b_cannot_see_tenant_a_revaluations_and_disposals(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $asset = Asset::create([
            'asset_code' => 'AST-A-003',
            'name' => 'A building',
            'purchase_price' => 200000.00,
            'salvage_value'  => 20000.00,
            'useful_life_months' => 240,
            'depreciation_method' => 'straight_line',
            'status' => 'active',
        ]);
        $reval = AssetRevaluationLog::create([
            'asset_id' => $asset->id,
            'appraisal_date' => '2026-06-01',
            'previous_value' => 200000.00,
            'appraisal_value' => 220000.00,
            'adjustment_amount' => 20000.00,
            'adjustment_type' => 'surplus',
            'appraiser' => 'A Co. Appraisers',
        ]);
        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => '2026-06-15',
            'disposal_type' => 'sale',
            'sale_price' => 210000.00,
            'final_nbv' => 200000.00,
            'gain_loss' => 10000.00,
            'gain_loss_type' => 'gain',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, AssetRevaluationLog::count());
        $this->assertNull(AssetRevaluationLog::find($reval->id));
        $this->assertSame(0, AssetDisposal::count());
        $this->assertNull(AssetDisposal::find($disposal->id));
    }
}
