<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MaintenanceLog;
use App\Tenants\Modules\Fleet\Resources\MaintenanceLogResource;
use App\Tenants\Modules\Fleet\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MaintenanceLogController extends Controller
{
    use Paginates;

    public function __construct(private VehicleService $vehicleService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceLog::class);

        $query = MaintenanceLog::query()->orderBy('service_date', 'desc');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(MaintenanceLogResource::class, $paginator, $request);
    }

    public function store(Request $request): MaintenanceLogResource
    {
        $this->authorize('create', MaintenanceLog::class);

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

    /**
     * Update an existing maintenance log.
     *
     * `mileage_at_service` and `vehicle_id` are NOT editable — the mileage is
     * a recorded fact (changing it after the fact would leave vehicle
     * current_mileage stale), and moving a log between vehicles isn't a
     * supported workflow. Use destroy + create instead.
     */
    public function update(Request $request, MaintenanceLog $maintenanceLog): MaintenanceLogResource
    {
        $this->authorize('update', $maintenanceLog);

        $data = $request->validate([
            'service_type' => 'sometimes|required|string',
            'service_date' => 'sometimes|required|date',
            'cost' => 'sometimes|required|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ]);

        $maintenanceLog->update($data);
        return new MaintenanceLogResource($maintenanceLog->refresh());
    }

    public function destroy(MaintenanceLog $maintenanceLog): Response
    {
        $this->authorize('delete', $maintenanceLog);

        // Hard delete — MaintenanceLog intentionally has no SoftDeletes.
        // Removing the row doesn't roll the vehicle's current_mileage back;
        // mileage stays at whatever the most recent un-deleted log advanced it
        // to (we can't recover the previous state without a snapshot table).
        $maintenanceLog->delete();

        return response()->noContent();
    }
}
