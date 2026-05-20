<?php

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\FuelLog;
use App\Tenants\Modules\Fleet\Resources\FuelLogResource;
use App\Tenants\Modules\Fleet\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelLogController extends Controller
{
    use Paginates;

    protected $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = FuelLog::query()->orderBy('fill_date', 'desc');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(FuelLogResource::class, $paginator, $request);
    }

    public function store(Request $request): FuelLogResource
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'fill_date' => 'required|date',
            'liters' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'mileage_at_fill' => 'required|integer|min:0',
            'driver_id' => 'nullable|exists:employees,id',
        ]);

        $log = $this->vehicleService->logFuel($data);
        return new FuelLogResource($log);
    }
}
