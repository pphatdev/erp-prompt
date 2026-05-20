<?php

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MaintenanceLog;
use App\Tenants\Modules\Fleet\Resources\MaintenanceLogResource;
use App\Tenants\Modules\Fleet\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceLogController extends Controller
{
    use Paginates;

    protected $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceLog::query()->orderBy('service_date', 'desc');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(MaintenanceLogResource::class, $paginator, $request);
    }

    public function store(Request $request): MaintenanceLogResource
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_type' => 'required|string',
            'service_date' => 'required|date',
            'mileage_at_service' => 'required|integer|min:0',
            'cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $log = $this->vehicleService->logMaintenance($data);
        return new MaintenanceLogResource($log);
    }
}
