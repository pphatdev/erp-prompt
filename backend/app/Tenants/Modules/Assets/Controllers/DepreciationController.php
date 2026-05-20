<?php

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Tenants\Modules\Assets\Resources\DepreciationLogResource;
use App\Tenants\Modules\Assets\Services\DepreciationService;
use Illuminate\Http\Request;

class DepreciationController extends Controller
{
    protected $depreciationService;

    public function __construct(DepreciationService $depreciationService)
    {
        $this->depreciationService = $depreciationService;
    }

    /**
     * Trigger depreciation calculation for an asset.
     */
    public function calculate(Request $request, Asset $asset): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'period_date' => 'required|date',
        ]);

        $log = $this->depreciationService->calculateDepreciation($asset, $request->input('period_date'));

        if (!$log) {
            return response()->json(['message' => 'Asset is fully depreciated or inactive.'], 400);
        }

        return response()->json(new DepreciationLogResource($log), 201);
    }
}
