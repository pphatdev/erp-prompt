<?php

namespace Tests\Feature;

class FixedAssetsTest extends TenantTestCase
{
    /**
     * Test Assets Module endpoints listing accessibility.
     */
    public function test_assets_module_endpoints()
    {
        $this->tenantRequest('GET', '/api/v1/assets')->assertStatus(200);
    }

    /**
     * Test Assets module features including asset registration and straight-line depreciation calculations.
     */
    public function test_fixed_assets_features_workflow()
    {
        // 1. Register an Asset
        $assetPayload = [
            'asset_tag' => 'AST-MB-2026',
            'name' => 'MacBook Pro M3 Max',
            'category' => 'IT Equipment',
            'purchase_date' => '2026-01-01',
            'purchase_cost' => 3500.00,
            'salvage_value' => 500.00,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
        ];

        $assetResponse = $this->tenantRequest('POST', '/api/v1/assets', $assetPayload);
        $assetResponse->assertStatus(201);
        $assetId = $assetResponse->json('data.id');
        $this->assertNotNull($assetId);

        // 2. Trigger Depreciation Calculation
        $depPayload = [
            'period_date' => '2026-05-19',
        ];

        $depResponse = $this->tenantRequest('POST', "/api/v1/assets/{$assetId}/depreciate", $depPayload);
        $depResponse->assertStatus(201)->assertJsonStructure([
            'id',
            'asset_id',
            'period_date',
            'depreciation_amount',
            'accumulated_depreciation',
            'book_value',
        ]);

        $this->assertDatabaseHas('depreciation_logs', [
            'asset_id' => $assetId,
            'period_date' => '2026-05-19',
        ]);
    }
}
