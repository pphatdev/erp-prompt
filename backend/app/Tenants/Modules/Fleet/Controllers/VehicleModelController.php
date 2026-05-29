<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\VehicleModel;
use App\Tenants\Modules\Fleet\Requests\StoreVehicleModelRequest;
use App\Tenants\Modules\Fleet\Resources\VehicleModelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleModelController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleModel::class);

        $paginator = $this->paginateQuery(
            VehicleModel::query()->orderBy('make')->orderBy('model'),
            $request,
        );

        return $this->paginatedResponse(VehicleModelResource::class, $paginator, $request);
    }

    public function store(StoreVehicleModelRequest $request): VehicleModelResource
    {
        $this->authorize('create', VehicleModel::class);

        return new VehicleModelResource(VehicleModel::create($request->validated()));
    }

    public function show(VehicleModel $vehicleModel): VehicleModelResource
    {
        $this->authorize('view', $vehicleModel);

        return new VehicleModelResource($vehicleModel);
    }

    public function update(StoreVehicleModelRequest $request, VehicleModel $vehicleModel): VehicleModelResource
    {
        $this->authorize('update', $vehicleModel);

        $vehicleModel->update($request->validated());

        return new VehicleModelResource($vehicleModel);
    }

    public function destroy(VehicleModel $vehicleModel): JsonResponse
    {
        $this->authorize('delete', $vehicleModel);

        $vehicleModel->delete();

        return response()->json(['message' => 'Vehicle model removed.'], 200);
    }
}
