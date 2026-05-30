<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\AssetVerificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AuditCampaignService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AssetAuditCampaign
    {
        return AssetAuditCampaign::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AssetAuditCampaign $campaign, array $data): AssetAuditCampaign
    {
        if ($campaign->status !== AssetAuditCampaign::STATUS_DRAFT) {
            // Once active, only name/description/assigned_to may shift; the
            // window/frequency drove the snapshot and changing them mid-flight
            // would corrupt the reconciliation count.
            $data = array_intersect_key($data, array_flip(['name', 'description', 'assigned_to']));
        }

        $campaign->update($data);

        return $campaign->refresh();
    }

    /**
     * Move a campaign from draft -> active, snapshotting the count of assets
     * in scope at that moment so the reconciliation report has a stable
     * denominator even if the asset register changes during the campaign.
     */
    public function start(AssetAuditCampaign $campaign): AssetAuditCampaign
    {
        if ($campaign->status !== AssetAuditCampaign::STATUS_DRAFT) {
            throw new RuntimeException("Campaign {$campaign->id} is not in draft (current: {$campaign->status}).");
        }

        return DB::transaction(function () use ($campaign) {
            $campaign->update([
                'status'               => AssetAuditCampaign::STATUS_ACTIVE,
                'started_at'           => now(),
                'expected_asset_count' => Asset::query()->where('status', 'active')->count(),
            ]);

            return $campaign->refresh();
        });
    }

    /**
     * Close the campaign. Computes per-asset reconciliation: every active
     * asset that did NOT receive a verification in the window is flagged
     * `missing` via a synthetic verification log so the report is complete.
     */
    public function complete(AssetAuditCampaign $campaign): AssetAuditCampaign
    {
        if ($campaign->status !== AssetAuditCampaign::STATUS_ACTIVE) {
            throw new RuntimeException("Campaign {$campaign->id} is not active (current: {$campaign->status}).");
        }

        return DB::transaction(function () use ($campaign) {
            $verifiedAssetIds = $campaign->verifications()->pluck('asset_id')->all();

            $missing = Asset::query()
                ->where('status', 'active')
                ->whereNotIn('id', $verifiedAssetIds)
                ->get(['id', 'condition', 'location_id']);

            foreach ($missing as $asset) {
                AssetVerificationLog::create([
                    'campaign_id'           => $campaign->id,
                    'asset_id'              => $asset->id,
                    'verified_by'           => null,
                    'verified_at'           => now(),
                    'previous_condition'    => $asset->condition,
                    'new_condition'         => $asset->condition,
                    'previous_location_id'  => $asset->location_id,
                    'new_location_id'       => $asset->location_id,
                    'reconciliation_status' => AssetVerificationLog::STATUS_MISSING,
                    'notes'                 => 'Auto-flagged missing on campaign close (no verification recorded in window).',
                ]);
            }

            $campaign->update([
                'status'       => AssetAuditCampaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $campaign->refresh();
        });
    }

    public function cancel(AssetAuditCampaign $campaign): AssetAuditCampaign
    {
        if (in_array($campaign->status, [AssetAuditCampaign::STATUS_COMPLETED, AssetAuditCampaign::STATUS_CANCELLED], true)) {
            throw new RuntimeException("Campaign {$campaign->id} is already {$campaign->status}.");
        }
        $campaign->update(['status' => AssetAuditCampaign::STATUS_CANCELLED]);

        return $campaign->refresh();
    }

    /**
     * Aggregate counts for a campaign's reconciliation report.
     *
     * @return array<string, int>
     */
    public function reconciliation(AssetAuditCampaign $campaign): array
    {
        $expected = (int) ($campaign->expected_asset_count ?? Asset::query()->where('status', 'active')->count());

        $byStatus = $campaign->verifications()
            ->selectRaw('reconciliation_status, COUNT(*) AS total')
            ->groupBy('reconciliation_status')
            ->pluck('total', 'reconciliation_status');

        $matched     = (int) ($byStatus[AssetVerificationLog::STATUS_MATCHED]     ?? 0);
        $moved       = (int) ($byStatus[AssetVerificationLog::STATUS_MOVED]       ?? 0);
        $damaged     = (int) ($byStatus[AssetVerificationLog::STATUS_DAMAGED]     ?? 0);
        $missing     = (int) ($byStatus[AssetVerificationLog::STATUS_MISSING]     ?? 0);
        $transferred = (int) ($byStatus[AssetVerificationLog::STATUS_TRANSFERRED] ?? 0);

        $scanned   = $matched + $moved + $damaged + $transferred;
        $remaining = max(0, $expected - $scanned - $missing);

        return [
            'expected'    => $expected,
            'scanned'     => $scanned,
            'matched'     => $matched,
            'moved'       => $moved,
            'damaged'     => $damaged,
            'transferred' => $transferred,
            'missing'     => $missing,
            'remaining'   => $remaining,
            'progress'    => $expected > 0 ? round(($scanned + $missing) / $expected * 100, 2) : 0.0,
        ];
    }

    /**
     * Bi-annual / annual cycle helper used by future schedulers. Validates the
     * frequency string and produces sensible default start/end if omitted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeFrequencyWindow(array $data): array
    {
        $frequency = strtolower($data['frequency'] ?? 'biannual');
        if (!in_array($frequency, ['annual', 'biannual', 'quarterly', 'adhoc'], true)) {
            throw new InvalidArgumentException("Unsupported frequency: {$frequency}");
        }
        $data['frequency'] = $frequency;

        if (empty($data['starts_at'])) {
            $data['starts_at'] = Carbon::now()->toDateString();
        }
        if (empty($data['ends_at'])) {
            $data['ends_at'] = Carbon::parse($data['starts_at'])->addDays(match ($frequency) {
                'annual'    => 30,
                'biannual'  => 21,
                'quarterly' => 14,
                'adhoc'     => 7,
            })->toDateString();
        }

        return $data;
    }
}
