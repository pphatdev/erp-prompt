<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AssetAuditCampaign;
use App\Tenants\Modules\Assets\Resources\AssetAuditCampaignResource;
use App\Tenants\Modules\Assets\Services\AuditCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetAuditCampaignController extends Controller
{
    use Paginates;

    public function __construct(private readonly AuditCampaignService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetAuditCampaign::class);

        $query = AssetAuditCampaign::query()->orderByDesc('starts_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($frequency = $request->query('frequency')) {
            $query->where('frequency', $frequency);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetAuditCampaignResource::class, $paginator, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AssetAuditCampaign::class);

        $data = $this->validatePayload($request, false);
        $data = $this->service->normalizeFrequencyWindow($data);
        $campaign = $this->service->create($data);

        return (new AssetAuditCampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function show(AssetAuditCampaign $campaign): AssetAuditCampaignResource
    {
        $this->authorize('view', $campaign);

        $campaign->setAttribute('reconciliation', $this->service->reconciliation($campaign));

        return new AssetAuditCampaignResource($campaign);
    }

    public function update(Request $request, AssetAuditCampaign $campaign): AssetAuditCampaignResource
    {
        $this->authorize('update', $campaign);

        $data = $this->validatePayload($request, true);
        $campaign = $this->service->update($campaign, $data);

        return new AssetAuditCampaignResource($campaign);
    }

    public function destroy(AssetAuditCampaign $campaign): Response
    {
        $this->authorize('delete', $campaign);

        $this->service->cancel($campaign);
        $campaign->delete();

        return response()->noContent();
    }

    public function start(AssetAuditCampaign $campaign): AssetAuditCampaignResource
    {
        $this->authorize('start', $campaign);
        return new AssetAuditCampaignResource($this->service->start($campaign));
    }

    public function complete(AssetAuditCampaign $campaign): AssetAuditCampaignResource
    {
        $this->authorize('complete', $campaign);
        $campaign = $this->service->complete($campaign);
        $campaign->setAttribute('reconciliation', $this->service->reconciliation($campaign));
        return new AssetAuditCampaignResource($campaign);
    }

    public function reconciliation(AssetAuditCampaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        return response()->json(['data' => $this->service->reconciliation($campaign)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate): array
    {
        $rules = [
            'name'        => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:255',
            'description' => 'sometimes|nullable|string',
            'frequency'   => 'sometimes|nullable|string|in:annual,biannual,quarterly,adhoc',
            'startsAt'    => 'sometimes|nullable|date',
            'endsAt'      => 'sometimes|nullable|date|after_or_equal:startsAt',
            'assignedTo'  => 'sometimes|nullable|uuid|exists:users,id',
        ];

        $v = $request->validate($rules);

        return [
            'name'        => $v['name']        ?? null,
            'description' => $v['description'] ?? null,
            'frequency'   => $v['frequency']   ?? null,
            'starts_at'   => $v['startsAt']    ?? null,
            'ends_at'     => $v['endsAt']      ?? null,
            'assigned_to' => $v['assignedTo']  ?? null,
        ];
    }
}
