<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Vehicle;
use App\Tenants\Modules\Fleet\Resources\VehicleResource;
use App\Tenants\Modules\Fleet\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    use Paginates;

    public function __construct(private VehicleService $vehicleService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $paginator = $this->paginateQuery(Vehicle::query()->orderBy('registration_number'), $request);

        return $this->paginatedResponse(VehicleResource::class, $paginator, $request);
    }

    public function store(Request $request): VehicleResource
    {
        $this->authorize('create', Vehicle::class);

        $data = $request->validate([
            'registration_number' => 'required|string|unique:vehicles,registration_number',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer',
            'vin' => 'nullable|string',
            'status' => 'nullable|string|in:active,maintenance,retired',
            'current_mileage' => 'nullable|integer|min:0',
        ]);

        $vehicle = Vehicle::create($data);
        return new VehicleResource($vehicle);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle->load('maintenanceLogs', 'fuelLogs'));
    }

    /**
     * Update an existing vehicle's identity / classification fields.
     *
     * `current_mileage` is intentionally NOT in the validator — the only way
     * to advance vehicle mileage is through a maintenance or fuel log (service
     * methods that enforce the monotonic invariant). Letting `update` move
     * mileage directly would bypass that check.
     */
    public function update(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'registration_number' => 'sometimes|required|string|unique:vehicles,registration_number,' . $vehicle->id,
            'make' => 'sometimes|required|string',
            'model' => 'sometimes|required|string',
            'year' => 'sometimes|required|integer',
            'vin' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|in:active,maintenance,retired',
        ]);

        $vehicle->update($data);
        return new VehicleResource($vehicle->refresh());
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        $this->vehicleService->archiveVehicle($vehicle);

        return response()->noContent();
    }

    /**
     * Bulk-archive vehicles. Body: `{ ids: [uuid, ...] }`. Returns the
     * `{ deleted, skipped, missing }` envelope from design.md §14.5 so the
     * frontend can render a per-bucket toast on partial success.
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $this->authorize('delete', Vehicle::class);

        $data = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'required|uuid',
        ]);

        $result = $this->vehicleService->bulkArchiveVehicles($data['ids']);

        return response()->json($result, 200);
    }

    /**
     * Upload (or replace) a vehicle photo. Mirrors EmployeeController::uploadAvatar:
     * public disk, 2 MB ceiling, tenant-scoped subdir, prior file removed first
     * so we never orphan blobs. Photos are public assets (shown in the list
     * thumbnail + details hero) — sensitive uploads like fuel receipts use the
     * signed-URL pattern from design.md §11 instead.
     */
    public function uploadImage(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $request->validate(['image' => 'required|image|max:2048']);

        if ($vehicle->image_path) {
            Storage::disk('public')->delete($vehicle->image_path);
        }
        $path = $request->file('image')->store($this->imageDir(), 'public');
        $vehicle->update(['image_path' => $path]);

        return new VehicleResource($vehicle->fresh());
    }

    public function deleteImage(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        if ($vehicle->image_path) {
            Storage::disk('public')->delete($vehicle->image_path);
            $vehicle->update(['image_path' => null]);
        }

        return new VehicleResource($vehicle->fresh());
    }

    private function imageDir(): string
    {
        // `tenant('id')` returns null because the Tenant model uses `handle`
        // as its key — see EmployeeController::imageDir for the same pattern.
        $key = tenant()?->getTenantKey() ?? 'default';
        return "vehicles/{$key}";
    }
}
