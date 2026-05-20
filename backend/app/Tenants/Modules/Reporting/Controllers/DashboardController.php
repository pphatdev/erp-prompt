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
        // Load widgets with the dashboard
        return new DashboardResource($dashboard->load('widgets'));
    }
}
