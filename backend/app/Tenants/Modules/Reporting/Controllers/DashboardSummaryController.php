<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\Reporting\Services\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request, DashboardSummaryService $service): JsonResponse
    {
        return response()->json($service->build($request->user()));
    }
}
