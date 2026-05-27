<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Resources\WarehouseResource;
use App\Tenants\Modules\Inventory\Services\WarehouseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    use Paginates;

    public function __construct(private readonly WarehouseService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Warehouse::class);

        $query = $this->service->buildQuery();
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('code', 'ilike', $like)
                ->orWhere('city', 'ilike', $like));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->paginatedResponse(WarehouseResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): WarehouseResource|JsonResponse
    {
        Gate::authorize('create', Warehouse::class);
        $data = $this->validatePayload($request);

        try {
            $w = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new WarehouseResource($w);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('view', $warehouse);
        return response()->json([
            'data'      => (new WarehouseResource($warehouse->load('manager')))->toArray(request()),
            'onHand'    => $warehouse->onHandStock(),
            'inventory' => $this->service->stockByProduct($warehouse),
        ]);
    }

    public function update(Request $request, Warehouse $warehouse): WarehouseResource|JsonResponse
    {
        Gate::authorize('update', $warehouse);
        $data = $this->validatePayload($request, $warehouse);

        try {
            $w = $this->service->update($warehouse, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new WarehouseResource($w);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('delete', $warehouse);

        try {
            $this->service->archive($warehouse);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Warehouse archived.']);
    }

    private function validatePayload(Request $request, ?Warehouse $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'code'         => "{$req}|string|max:40",
            'name'         => "{$req}|string|max:255",
            'location'     => 'sometimes|nullable|string|max:255',
            'manager_id'   => 'sometimes|nullable|uuid|exists:users,id',
            'address_line' => 'sometimes|nullable|string|max:255',
            'city'         => 'sometimes|nullable|string|max:120',
            'country'      => 'sometimes|nullable|string|size:2',
            'capacity'     => 'sometimes|nullable|integer|min:0',
            'is_active'    => 'sometimes|boolean',
            'notes'        => 'sometimes|nullable|string|max:2000',
        ]);
    }
}
