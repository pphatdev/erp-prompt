<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\AssetVerificationLog;
use App\Tenants\Modules\Assets\Resources\AssetResource;
use App\Tenants\Modules\Assets\Resources\AssetVerificationLogResource;
use App\Tenants\Modules\Assets\Services\AssetVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetVerificationController extends Controller
{
    use Paginates;

    public function __construct(private readonly AssetVerificationService $service)
    {
    }

    /**
     * Filterable log of every verification scan recorded in the tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = AssetVerificationLog::query()->orderByDesc('verified_at');
        if ($campaignId = $request->query('campaignId')) {
            $query->where('campaign_id', $campaignId);
        }
        if ($assetId = $request->query('assetId')) {
            $query->where('asset_id', $assetId);
        }
        if ($status = $request->query('reconciliationStatus')) {
            $query->where('reconciliation_status', $status);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetVerificationLogResource::class, $paginator, $request);
    }

    /**
     * Field-scan resolver. The mobile app hits this immediately after the
     * camera decodes a QR — the response gives the asset profile plus the
     * active campaign context (if any) so the scanner UI can decide whether
     * to render a verify-now form.
     */
    public function profile(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $ctx = $this->service->profileForScan($asset);

        return response()->json([
            'data' => [
                'asset'          => (new AssetResource($ctx['asset']))->toArray(request()),
                'activeCampaign' => $ctx['activeCampaign']
                    ? (new \App\Tenants\Modules\Assets\Resources\AssetAuditCampaignResource($ctx['activeCampaign']))->toArray(request())
                    : null,
                'alreadyScanned' => $ctx['alreadyScanned'],
                'lastVerifiedAt' => $ctx['lastVerifiedAt'],
            ],
        ]);
    }

    /**
     * Record a verification scan against an asset. If `campaignId` is supplied,
     * the campaign must be `active`.
     */
    public function store(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'campaignId'           => 'sometimes|nullable|uuid|exists:asset_audit_campaigns,id',
            'newCondition'         => 'sometimes|nullable|string|in:Excellent,Good,Fair,Poor,Damaged',
            'newLocationId'        => 'sometimes|nullable|string|max:255',
            'reconciliationStatus' => 'sometimes|nullable|string|in:matched,moved,damaged,missing,transferred',
            'notes'                => 'sometimes|nullable|string',
            'verifiedAt'           => 'sometimes|nullable|date',
        ]);

        $campaign = isset($validated['campaignId'])
            ? AssetAuditCampaign::query()->find($validated['campaignId'])
            : null;

        $payload = [];
        if (array_key_exists('newCondition', $validated))         { $payload['new_condition']         = $validated['newCondition']; }
        if (array_key_exists('newLocationId', $validated))        { $payload['new_location_id']       = $validated['newLocationId']; }
        if (array_key_exists('reconciliationStatus', $validated)) { $payload['reconciliation_status'] = $validated['reconciliationStatus']; }
        if (array_key_exists('notes', $validated))                { $payload['notes']                 = $validated['notes']; }
        if (array_key_exists('verifiedAt', $validated))           { $payload['verified_at']           = $validated['verifiedAt']; }

        $log = $this->service->record($asset, $payload, $campaign);

        return (new AssetVerificationLogResource($log))->response()->setStatusCode(201);
    }
}
