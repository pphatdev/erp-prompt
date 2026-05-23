<?php

namespace App\Tenants\Modules\Reporting\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Dashboard;
use App\Tenants\Modules\Reporting\Resources\DashboardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Dashboard::class);

        $query = Dashboard::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('is_default', true);
            })
            ->orderBy('name');

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(DashboardResource::class, $paginator, $request);
    }

    public function store(Request $request): DashboardResource
    {
        $this->authorize('create', Dashboard::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
        ]);

        $data['user_id'] = $request->user()->id;

        $dashboard = Dashboard::create($data);
        return new DashboardResource($dashboard);
    }

    public function show(Dashboard $dashboard): DashboardResource
    {
        $this->authorize('view', $dashboard);

        // Load widgets with the dashboard
        return new DashboardResource($dashboard->load('widgets'));
    }

    /**
     * Export the dashboard payload (config + widget snapshots) as JSON.
     * Gated by `reporting.dashboard.export` — Dashboard Viewers can pull
     * the data for their own consumption without write access. Returns a
     * download response so browsers save it as a file by default.
     */
    public function export(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('export', $dashboard);

        $payload = (new DashboardResource($dashboard->load('widgets')))
            ->resolve();

        $filename = 'dashboard-' . $dashboard->id . '-' . now()->format('Ymd-His') . '.json';

        return response()->json(['data' => $payload], 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
