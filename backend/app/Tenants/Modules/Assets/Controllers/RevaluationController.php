<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetRevaluationLog;
use App\Tenants\Modules\Assets\Resources\AssetRevaluationResource;
use App\Tenants\Modules\Assets\Services\RevaluationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevaluationController extends Controller
{
    use Paginates;

    public function __construct(private readonly RevaluationService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = AssetRevaluationLog::query()->orderByDesc('appraisal_date');
        if ($assetId = $request->query('assetId')) {
            $query->where('asset_id', $assetId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetRevaluationResource::class, $paginator, $request);
    }

    public function store(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('revalue', $asset);

        $validated = $request->validate([
            'appraisalValue' => 'required|numeric|min:0',
            'appraiser'      => 'sometimes|nullable|string|max:255',
            'notes'          => 'sometimes|nullable|string',
            'appraisalDate'  => 'sometimes|nullable|date',
        ]);

        $log = $this->service->revalue(
            asset: $asset,
            appraisalValue: (float) $validated['appraisalValue'],
            appraiser: $validated['appraiser'] ?? null,
            notes: $validated['notes'] ?? null,
            appraisalDate: isset($validated['appraisalDate']) ? Carbon::parse($validated['appraisalDate']) : null,
        );

        return (new AssetRevaluationResource($log))->response()->setStatusCode(201);
    }
}
