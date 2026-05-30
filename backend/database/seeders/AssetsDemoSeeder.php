<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\AssetVerificationLog;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\Assets\Services\AssetService;
use App\Tenants\Modules\Assets\Services\AuditCampaignService;
use App\Tenants\Modules\Assets\Services\DepreciationService;
use App\Tenants\Modules\Assets\Services\DisposalService;
use App\Tenants\Modules\Assets\Services\RevaluationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed Asset demo data: a tenant-realistic register with depreciation history,
 * one revaluation, one disposal, plus an active audit campaign with partial
 * scans. All side-effects go through the real services so the GL journal
 * entries, accumulated_depreciation values, and verification logs match what
 * production runs would produce.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\AssetsDemoSeeder" --option="force=true"
 *
 * Idempotency:
 *  - Assets are keyed on `asset_code` (composite (tenant_id, asset_code) unique).
 *    Re-runs skip already-seeded codes — they don't append a second history.
 *  - Audit campaign is keyed on `name`.
 */
class AssetsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assets')) {
            return;
        }

        $assetService        = app(AssetService::class);
        $depreciationService = app(DepreciationService::class);
        $revaluationService  = app(RevaluationService::class);
        $disposalService     = app(DisposalService::class);
        $campaignService     = app(AuditCampaignService::class);

        $custodians = Schema::hasTable('employees')
            ? Employee::query()->take(5)->pluck('id')->all()
            : [];

        $blueprint = [
            ['code' => 'AST-DEMO-001', 'name' => 'MacBook Pro 16" M3 Max',     'category' => 'IT Equipment',     'vendor' => 'Apple',         'price' => 4200.00, 'salvage' => 400.00, 'life' => 36, 'method' => 'straight_line',         'condition' => 'Excellent', 'months_back' => 4, 'location' => 'HQ-Floor3-Desk12'],
            ['code' => 'AST-DEMO-002', 'name' => 'Dell PowerEdge R760 Server', 'category' => 'IT Equipment',     'vendor' => 'Dell',          'price' => 18500.00,'salvage' => 1500.00,'life' => 60, 'method' => 'declining_balance',     'condition' => 'Good',      'months_back' => 6, 'location' => 'DC-Rack5-U18'],
            ['code' => 'AST-DEMO-003', 'name' => 'Cisco Catalyst 9300 Switch', 'category' => 'Network Hardware', 'vendor' => 'Cisco',         'price' => 6800.00, 'salvage' => 500.00, 'life' => 60, 'method' => 'straight_line',         'condition' => 'Good',      'months_back' => 8, 'location' => 'DC-Rack5-U24'],
            ['code' => 'AST-DEMO-004', 'name' => 'Herman Miller Aeron Chair',  'category' => 'Office Furniture', 'vendor' => 'Herman Miller', 'price' => 1700.00, 'salvage' => 150.00, 'life' => 84, 'method' => 'straight_line',         'condition' => 'Excellent', 'months_back' => 2, 'location' => 'HQ-Floor2-Desk04'],
            ['code' => 'AST-DEMO-005', 'name' => 'Conference Table — Walnut',  'category' => 'Office Furniture', 'vendor' => 'Steelcase',     'price' => 3200.00, 'salvage' => 200.00, 'life' => 120,'method' => 'straight_line',         'condition' => 'Good',      'months_back' => 5, 'location' => 'HQ-Floor4-BoardRoom'],
            ['code' => 'AST-DEMO-006', 'name' => 'Daikin 5HP AC Unit',         'category' => 'Building Services','vendor' => 'Daikin',        'price' => 2800.00, 'salvage' => 200.00, 'life' => 96, 'method' => 'sum_of_years_digits',   'condition' => 'Good',      'months_back' => 7, 'location' => 'HQ-Floor3-Server'],
            ['code' => 'AST-DEMO-007', 'name' => 'iPhone 15 Pro (Corporate)',  'category' => 'Mobile Devices',   'vendor' => 'Apple',         'price' => 1300.00, 'salvage' => 100.00, 'life' => 24, 'method' => 'straight_line',         'condition' => 'Excellent', 'months_back' => 3, 'location' => 'Mobile'],
            ['code' => 'AST-DEMO-008', 'name' => 'Sony FX3 Cinema Camera',     'category' => 'A/V Equipment',    'vendor' => 'Sony',          'price' => 3900.00, 'salvage' => 350.00, 'life' => 48, 'method' => 'declining_balance',     'condition' => 'Fair',      'months_back' => 10,'location' => 'HQ-Floor1-Studio'],
            ['code' => 'AST-DEMO-009', 'name' => 'Heavy-Duty Floor Polisher',  'category' => 'Cleaning Equipment','vendor'=> 'Karcher',        'price' => 980.00,  'salvage' => 50.00,  'life' => 60, 'method' => 'straight_line',         'condition' => 'Good',      'months_back' => 6, 'location' => 'HQ-Storage'],
            ['code' => 'AST-DEMO-010', 'name' => 'Smart Whiteboard Display',   'category' => 'A/V Equipment',    'vendor' => 'Samsung',       'price' => 2200.00, 'salvage' => 200.00, 'life' => 48, 'method' => 'straight_line',         'condition' => 'Excellent', 'months_back' => 1, 'location' => 'HQ-Floor4-BoardRoom'],
            ['code' => 'AST-DEMO-011', 'name' => 'Industrial Espresso Machine','category' => 'Office Furniture', 'vendor' => 'La Marzocco',   'price' => 5400.00, 'salvage' => 400.00, 'life' => 84, 'method' => 'straight_line',         'condition' => 'Good',      'months_back' => 4, 'location' => 'HQ-Floor2-Kitchen'],
            ['code' => 'AST-DEMO-012', 'name' => 'Legacy Fax Machine — Brother','category'=> 'IT Equipment',     'vendor' => 'Brother',       'price' => 480.00,  'salvage' => 20.00,  'life' => 60, 'method' => 'straight_line',         'condition' => 'Poor',      'months_back' => 30,'location' => 'HQ-Storage'],
        ];

        $createdAssets = [];
        foreach ($blueprint as $i => $row) {
            $existing = Asset::withTrashed()->where('asset_code', $row['code'])->first();
            if ($existing) {
                if ($existing->trashed()) { $existing->restore(); }
                $createdAssets[] = $existing;
                continue;
            }

            $custodian = $custodians[$i % max(1, count($custodians))] ?? null;
            $asset = $assetService->create([
                'asset_code'            => $row['code'],
                'name'                  => $row['name'],
                'category'              => $row['category'],
                'vendor_name'           => $row['vendor'],
                'serial_number'         => 'SN-' . strtoupper(substr(md5($row['code']), 0, 10)),
                'description'           => sprintf('%s — seeded demo asset.', $row['name']),
                'purchase_date'         => Carbon::now()->subMonths($row['months_back'])->toDateString(),
                'purchase_price'        => $row['price'],
                'salvage_value'         => $row['salvage'],
                'useful_life_months'    => $row['life'],
                'depreciation_method'   => $row['method'],
                'status'                => 'active',
                'condition'             => $row['condition'],
                'custodian_employee_id' => $custodian,
                'location_id'           => $row['location'],
            ]);
            $createdAssets[] = $asset;
        }

        // ---- Depreciation history -----------------------------------------
        // For each active asset, post up to 3 months of past depreciation runs.
        // Skipped silently when the GL accounts (1500 / 5400) are missing —
        // that's expected when the chart seeder hasn't run yet.
        foreach ($createdAssets as $asset) {
            if ($asset->status !== 'active') continue;
            // If the asset already has logs (re-run), don't append duplicates.
            if ($asset->depreciationLogs()->count() > 0) continue;

            $monthsToPost = min(3, max(0, (int) Carbon::parse($asset->purchase_date)->diffInMonths(Carbon::now())));
            for ($m = $monthsToPost; $m >= 1; $m--) {
                $period = Carbon::now()->subMonths($m)->endOfMonth();
                try {
                    $depreciationService->runDepreciationForAsset($asset->fresh(), $period);
                } catch (\Throwable $e) {
                    // GL period locked, missing chart accounts, fully-depreciated, etc.
                    break;
                }
            }
        }

        // ---- One revaluation surplus on AST-DEMO-005 (conference table) ----
        $revAsset = collect($createdAssets)->firstWhere('asset_code', 'AST-DEMO-005');
        if ($revAsset && $revAsset->revaluations()->count() === 0) {
            try {
                $revaluationService->revalue(
                    asset: $revAsset->fresh(),
                    appraisalValue: round((float) $revAsset->fresh()->net_book_value * 1.15, 2),
                    appraiser: 'Premier Valuation Services',
                    notes: 'Annual market reappraisal — demo seed.',
                    appraisalDate: Carbon::now()->subDays(20),
                );
            } catch (\Throwable) {}
        }

        // ---- Dispose AST-DEMO-012 (legacy fax) as scrap --------------------
        $disposalAsset = collect($createdAssets)->firstWhere('asset_code', 'AST-DEMO-012');
        if ($disposalAsset && $disposalAsset->status === 'active' && $disposalAsset->disposals()->count() === 0) {
            try {
                $disposalService->dispose(
                    asset: $disposalAsset->fresh(),
                    disposalType: 'scrap',
                    salePrice: 0,
                    disposalDate: Carbon::now()->subDays(5),
                    extra: ['notes' => 'End-of-life write-off — demo seed.'],
                );
            } catch (\Throwable) {}
        }

        // ---- Active audit campaign with partial scans ----------------------
        $campaignName = '2026 H1 Stock-take';
        $campaign = AssetAuditCampaign::withTrashed()->where('name', $campaignName)->first();
        if (!$campaign) {
            $campaign = $campaignService->create($campaignService->normalizeFrequencyWindow([
                'name'        => $campaignName,
                'description' => 'Demo seeded bi-annual physical verification campaign.',
                'frequency'   => 'biannual',
                'starts_at'   => Carbon::now()->subDays(3)->toDateString(),
                'ends_at'     => Carbon::now()->addDays(21)->toDateString(),
            ]));
            try {
                $campaign = $campaignService->start($campaign);
            } catch (\Throwable) {}
        }

        // Synthetic scans: matched on assets 1-2, moved on 3 (different room),
        // damaged on 8 (Camera Fair condition). Skip if the campaign already
        // has scans (re-run guard).
        if ($campaign && $campaign->status === 'active' && $campaign->verifications()->count() === 0) {
            $scanPlan = [
                ['code' => 'AST-DEMO-001', 'status' => AssetVerificationLog::STATUS_MATCHED, 'newCondition' => 'Excellent'],
                ['code' => 'AST-DEMO-002', 'status' => AssetVerificationLog::STATUS_MATCHED, 'newCondition' => 'Good'],
                ['code' => 'AST-DEMO-003', 'status' => AssetVerificationLog::STATUS_MOVED,   'newLocation' => 'DC-Rack6-U10'],
                ['code' => 'AST-DEMO-008', 'status' => AssetVerificationLog::STATUS_DAMAGED, 'newCondition' => 'Damaged'],
            ];

            foreach ($scanPlan as $plan) {
                $asset = collect($createdAssets)->firstWhere('asset_code', $plan['code']);
                if (!$asset) continue;
                $fresh = $asset->fresh();
                if (!$fresh || $fresh->trashed()) continue;

                AssetVerificationLog::create([
                    'campaign_id'           => $campaign->id,
                    'asset_id'              => $fresh->id,
                    'verified_by'           => null,
                    'verified_at'           => Carbon::now()->subDays(1),
                    'previous_condition'    => $fresh->condition,
                    'new_condition'         => $plan['newCondition'] ?? $fresh->condition,
                    'previous_location_id'  => $fresh->location_id,
                    'new_location_id'       => $plan['newLocation'] ?? $fresh->location_id,
                    'reconciliation_status' => $plan['status'],
                    'notes'                 => 'Demo seeded field scan.',
                ]);

                // Mirror the real flow: persist condition/location drift on the asset.
                $update = [];
                if (isset($plan['newCondition']) && $plan['newCondition'] !== $fresh->condition) {
                    $update['condition'] = $plan['newCondition'];
                }
                if (isset($plan['newLocation']) && $plan['newLocation'] !== $fresh->location_id) {
                    $update['location_id'] = $plan['newLocation'];
                }
                if (!empty($update)) {
                    $fresh->update($update);
                }
            }
        }
    }
}
