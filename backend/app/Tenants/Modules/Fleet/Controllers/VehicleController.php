<?php

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Vehicle;
use App\Tenants\Modules\Fleet\Resources\VehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(Vehicle::query()->orderBy('registration_number'), $request);

        return $this->paginatedResponse(VehicleResource::class, $paginator, $request);
    }

    public function store(Request $request): VehicleResource
    {
        $data = $request->validate([
            'registration_number' => 'required|string|unique:vehicles,registration_number',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer',
            'vin' => 'nullable|string',
            'status' => 'nullable|string',
            'current_mileage' => 'nullable|integer|min:0',
        ]);

        $vehicle = Vehicle::create($data);
        return new VehicleResource($vehicle);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($vehicle->load('maintenanceLogs', 'fuelLogs'));
    }
}
