<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\StockTransfer;
use App\Tenants\Modules\Inventory\Resources\StockTransferResource;
use App\Tenants\Modules\Inventory\Services\StockTransferService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockTransferController extends Controller
{
    use Paginates;

    public function __construct(private readonly StockTransferService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockTransfer::class);

        $query = $this->service->buildQuery();
        if ($status = $request->query('status'))               $query->where('status', $status);
        if ($from = $request->query('from_warehouse_id'))      $query->where('from_warehouse_id', $from);
        if ($to = $request->query('to_warehouse_id'))          $query->where('to_warehouse_id', $to);

        return $this->paginatedResponse(StockTransferResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        Gate::authorize('view', $stockTransfer);
        return new StockTransferResource($stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items']));
    }

    public function store(Request $request): StockTransferResource|JsonResponse
    {
        Gate::authorize('create', StockTransfer::class);

        $data = $request->validate([
            'from_warehouse_id'    => 'required|uuid|exists:warehouses,id',
            'to_warehouse_id'      => 'required|uuid|exists:warehouses,id',
            'notes'                => 'sometimes|nullable|string|max:1000',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|uuid|exists:products,id',
            'items.*.variant_id'   => 'sometimes|nullable|uuid|exists:product_variants,id',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.notes'        => 'sometimes|nullable|string|max:500',
        ]);

        try {
            return new StockTransferResource($this->service->create($data));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dispatch_(StockTransfer $stockTransfer): StockTransferResource|JsonResponse
    {
        Gate::authorize('dispatch', $stockTransfer);
        try {
            return new StockTransferResource($this->service->dispatch($stockTransfer));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function receive(Request $request, StockTransfer $stockTransfer): StockTransferResource|JsonResponse
    {
        Gate::authorize('receive', $stockTransfer);

        $data = $request->validate([
            'items'   => 'sometimes|array',
            'items.*' => 'numeric|min:0',
        ]);

        try {
            return new StockTransferResource($this->service->receive($stockTransfer, $data['items'] ?? []));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): StockTransferResource|JsonResponse
    {
        Gate::authorize('cancel', $stockTransfer);

        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);
        try {
            return new StockTransferResource($this->service->cancel($stockTransfer, $data['reason'] ?? null));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
