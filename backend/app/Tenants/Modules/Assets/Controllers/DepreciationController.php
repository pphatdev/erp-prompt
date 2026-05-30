<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Models\Tenant\DepreciationLog;
use App\Tenants\Modules\Assets\Resources\DepreciationLogResource;
use App\Tenants\Modules\Assets\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepreciationController extends Controller
{
    use Paginates;

    public function __construct(private readonly DepreciationService $service)
    {
    }

    /**
     * Paginated depreciation history across all assets the user can see.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = DepreciationLog::query()->orderByDesc('period_date');
        if ($assetId = $request->query('assetId')) {
            $query->where('asset_id', $assetId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(DepreciationLogResource::class, $paginator, $request);
    }

    /**
     * Trigger the monthly depreciation calculation for a single asset. The
     * service handles the FMS journal posting + rollback on failure.
     */
    public function calculate(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('depreciate', $asset);

        $validated = $request->validate([
            'periodDate' => 'sometimes|nullable|date',
        ]);

        $period = isset($validated['periodDate'])
            ? Carbon::parse($validated['periodDate'])
            : null;

        $log = $this->service->runDepreciationForAsset($asset, $period);

        if (!$log) {
            return response()->json([
                'message' => 'Asset is fully depreciated, inactive, or has zero remaining depreciable amount.',
            ], 422);
        }

        return (new DepreciationLogResource($log))->response()->setStatusCode(201);
    }

    /**
     * Pure calculation preview — no DB writes, no FMS posting. Useful for the
     * frontend to show the next-month projection before scheduling.
     */
    public function preview(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $calc = $this->service->calculateNextMonthlyDepreciation($asset);

        return response()->json([
            'data' => [
                'assetId'              => $asset->id,
                'amount'               => (float) $calc->amount,
                'method'               => $calc->method,
                'accumulatedAfter'     => (float) $calc->accumulatedAfter,
                'netBookValueAfter'    => (float) $calc->nbvAfter,
            ],
        ]);
    }
}
