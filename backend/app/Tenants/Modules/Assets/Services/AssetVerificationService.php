<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\AssetVerificationLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssetVerificationService
{
    /**
     * Record one field scan against an asset. The asset's `condition` and
     * `location_id` columns are updated in the same transaction so the live
     * register reflects the latest scan.
     *
     * Reconciliation status is derived automatically:
     *   - moved      : new_location_id differs from previous
     *   - damaged    : new_condition is Damaged (any prior state)
     *   - transferred: callers passed reconciliation_status=transferred (handover)
     *   - matched    : everything aligned with the existing register row
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(Asset $asset, array $payload, ?AssetAuditCampaign $campaign = null): AssetVerificationLog
    {
        if ($campaign && $campaign->status !== AssetAuditCampaign::STATUS_ACTIVE) {
            throw new RuntimeException(
                "Cannot record a verification against campaign {$campaign->id} — status is {$campaign->status} (must be active)."
            );
        }

        $newCondition  = $payload['new_condition']   ?? $asset->condition;
        $newLocationId = array_key_exists('new_location_id', $payload)
            ? $payload['new_location_id']
            : $asset->location_id;

        $reconStatus = $this->resolveReconciliationStatus(
            asset: $asset,
            newCondition: $newCondition,
            newLocationId: $newLocationId,
            override: $payload['reconciliation_status'] ?? null,
        );

        return DB::transaction(function () use ($asset, $campaign, $newCondition, $newLocationId, $reconStatus, $payload) {
            $log = AssetVerificationLog::create([
                'campaign_id'           => $campaign?->id,
                'asset_id'              => $asset->id,
                'verified_by'           => Auth::id(),
                'verified_at'           => isset($payload['verified_at'])
                    ? Carbon::parse($payload['verified_at'])
                    : now(),
                'previous_condition'    => $asset->condition,
                'new_condition'         => $newCondition,
                'previous_location_id'  => $asset->location_id,
                'new_location_id'       => $newLocationId,
                'reconciliation_status' => $reconStatus,
                'notes'                 => $payload['notes'] ?? null,
            ]);

            $assetUpdate = [];
            if ($newCondition !== $asset->condition) {
                $assetUpdate['condition'] = $newCondition;
            }
            if ($newLocationId !== $asset->location_id) {
                $assetUpdate['location_id'] = $newLocationId;
            }
            if (!empty($assetUpdate)) {
                $asset->update($assetUpdate);
            }

            return $log->refresh();
        });
    }

    /**
     * Resolve the asset profile + outstanding-audit context for a QR scan.
     * Used by the mobile/web scanner immediately after the camera resolves
     * the QR's UUID — front-ends render a verify-here form from this payload.
     */
    public function profileForScan(Asset $asset, ?CarbonInterface $now = null): array
    {
        $now = $now ?? Carbon::now();

        $activeCampaign = AssetAuditCampaign::query()
            ->where('status', AssetAuditCampaign::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $now->toDateString())
            ->whereDate('ends_at', '>=', $now->toDateString())
            ->orderByDesc('started_at')
            ->first();

        $alreadyScanned = false;
        if ($activeCampaign) {
            $alreadyScanned = $activeCampaign->verifications()
                ->where('asset_id', $asset->id)
                ->exists();
        }

        $lastScan = $asset->verifications()
            ->orderByDesc('verified_at')
            ->first();

        return [
            'asset'           => $asset,
            'activeCampaign'  => $activeCampaign,
            'alreadyScanned'  => $alreadyScanned,
            'lastVerifiedAt'  => $lastScan?->verified_at,
        ];
    }

    private function resolveReconciliationStatus(Asset $asset, ?string $newCondition, ?string $newLocationId, ?string $override): string
    {
        if ($override && in_array($override, [
            AssetVerificationLog::STATUS_MATCHED,
            AssetVerificationLog::STATUS_MOVED,
            AssetVerificationLog::STATUS_DAMAGED,
            AssetVerificationLog::STATUS_MISSING,
            AssetVerificationLog::STATUS_TRANSFERRED,
        ], true)) {
            return $override;
        }

        if ($newCondition === 'Damaged') {
            return AssetVerificationLog::STATUS_DAMAGED;
        }
        if ($newLocationId !== $asset->location_id) {
            return AssetVerificationLog::STATUS_MOVED;
        }

        return AssetVerificationLog::STATUS_MATCHED;
    }
}
