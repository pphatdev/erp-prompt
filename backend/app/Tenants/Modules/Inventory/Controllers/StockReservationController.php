<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\StockReservation;
use App\Tenants\Modules\Inventory\Resources\StockReservationResource;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use App\Tenants\Modules\Inventory\Services\StockService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StockReservationController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly StockReservationService $service,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockReservation::class);

        $query = $this->service->buildQuery();
        if ($status = $request->query('status'))             $query->where('status', $status);
        if ($productId = $request->query('product_id'))      $query->where('product_id', $productId);
        if ($warehouseId = $request->query('warehouse_id'))  $query->where('warehouse_id', $warehouseId);
        if ($reference = $request->query('reference'))       $query->where('reference', $reference);

        return $this->paginatedResponse(StockReservationResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(StockReservation $stockReservation): StockReservationResource
    {
        Gate::authorize('view', $stockReservation);
        return new StockReservationResource($stockReservation->load(['product', 'warehouse', 'variant', 'actor']));
    }

    public function store(Request $request): StockReservationResource|JsonResponse
    {
        Gate::authorize('create', StockReservation::class);

        $data = $request->validate([
            'product_id'   => 'required|uuid|exists:products,id',
            'warehouse_id' => 'required|uuid|exists:warehouses,id',
            'variant_id'   => 'sometimes|nullable|uuid|exists:product_variants,id',
            'quantity'     => 'required|numeric|min:0.01',
            'reference'    => 'sometimes|nullable|string|max:255',
            'ttl_minutes'  => 'sometimes|integer|min:1|max:1440',
            'actor_id'     => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        try {
            $reservation = $this->service->reserve(
                $data,
                $data['ttl_minutes'] ?? StockReservationService::DEFAULT_TTL_MINUTES
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new StockReservationResource($reservation);
    }

    public function commit(StockReservation $stockReservation): StockReservationResource|JsonResponse
    {
        Gate::authorize('commit', $stockReservation);
        try {
            $r = $this->service->commit($stockReservation);
        } catch (DomainException | \Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new StockReservationResource($r);
    }

    public function cancel(Request $request, StockReservation $stockReservation): StockReservationResource|JsonResponse
    {
        Gate::authorize('update', $stockReservation);
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);
        try {
            $r = $this->service->cancel($stockReservation, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new StockReservationResource($r);
    }

    /**
     * GET /stock-reservations/availability?product_id=&warehouse_id=
     * Quick lookup for cart / POS UIs that want to validate a quantity
     * before posting a reserve. Returns physical + reserved + net so the
     * client can present a clear breakdown.
     */
    public function availability(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockReservation::class);

        $data = $request->validate([
            'product_id'   => 'required|uuid|exists:products,id',
            'warehouse_id' => 'required|uuid|exists:warehouses,id',
        ]);

        $physical = $this->stock->getPhysicalStock($data['product_id'], $data['warehouse_id']);
        $net      = $this->stock->getNetAvailableStock($data['product_id'], $data['warehouse_id']);

        return response()->json([
            'productId'      => $data['product_id'],
            'warehouseId'    => $data['warehouse_id'],
            'physicalStock'  => $physical,
            'reservedStock'  => max(0.0, $physical - $net),
            'availableStock' => $net,
        ]);
    }
}
