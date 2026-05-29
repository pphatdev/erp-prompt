<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\FuelLog;
use App\Tenants\Modules\Fleet\Resources\FuelLogResource;
use App\Tenants\Modules\Fleet\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FuelLogController extends Controller
{
    use Paginates;

    public function __construct(private VehicleService $vehicleService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FuelLog::class);

        $query = FuelLog::query()->orderBy('fill_date', 'desc');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(FuelLogResource::class, $paginator, $request);
    }

    public function store(Request $request): FuelLogResource
    {
        $this->authorize('create', FuelLog::class);

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

    /**
     * Update an existing fuel log.
     *
     * `mileage_at_fill` and `vehicle_id` are immutable for the same reasons
     * as MaintenanceLog::update — mileage is a recorded odometer reading, and
     * moving a log between vehicles is a destroy+create flow.
     */
    public function update(Request $request, FuelLog $fuelLog): FuelLogResource
    {
        $this->authorize('update', $fuelLog);

        $data = $request->validate([
            'fill_date' => 'sometimes|required|date',
            'liters' => 'sometimes|required|numeric|min:0',
            'cost' => 'sometimes|required|numeric|min:0',
            'driver_id' => 'sometimes|nullable|exists:employees,id',
        ]);

        $fuelLog->update($data);
        return new FuelLogResource($fuelLog->refresh());
    }

    public function destroy(FuelLog $fuelLog): Response
    {
        $this->authorize('delete', $fuelLog);

        // Hard delete — FuelLog intentionally has no SoftDeletes. See the
        // matching note in MaintenanceLogController::destroy for the
        // current_mileage caveat.
        $fuelLog->delete();

        return response()->noContent();
    }
}
