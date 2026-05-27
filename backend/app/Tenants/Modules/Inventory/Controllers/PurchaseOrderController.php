<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Tenants\Modules\Inventory\Resources\PurchaseOrderResource;
use App\Tenants\Modules\Inventory\Services\ProcurementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    use Paginates;

    public function __construct(private readonly ProcurementService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $query = $this->service->buildQuery();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }
        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($search = $request->query('search')) {
            $query->where('po_number', 'ilike', '%' . $search . '%');
        }

        return $this->paginatedResponse(PurchaseOrderResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        Gate::authorize('view', $purchaseOrder);
        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'warehouse', 'items']));
    }

    public function store(Request $request): PurchaseOrderResource|JsonResponse
    {
        Gate::authorize('create', PurchaseOrder::class);

        $data = $request->validate([
            'supplier_id'         => 'required|uuid|exists:suppliers,id',
            'warehouse_id'        => 'required|uuid|exists:warehouses,id',
            'order_date'          => 'sometimes|nullable|date',
            'expected_at'         => 'sometimes|nullable|date|after_or_equal:order_date',
            'notes'               => 'sometimes|nullable|string|max:2000',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|uuid|exists:products,id',
            'items.*.variant_id'  => 'sometimes|nullable|uuid|exists:product_variants,id',
            'items.*.variant_sku' => 'sometimes|nullable|string|max:60',
            'items.*.ordered_qty' => 'required|numeric|min:0.01',
            'items.*.unit_cost'   => 'sometimes|nullable|numeric|min:0',
            'items.*.notes'       => 'sometimes|nullable|string|max:500',
        ]);

        try {
            $po = $this->service->createDraft($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new PurchaseOrderResource($po);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        if (!$purchaseOrder->isDraft() && !$purchaseOrder->isCancelled()) {
            return response()->json([
                'message' => 'Only draft or cancelled POs can be deleted. Cancel first.',
            ], 422);
        }
        $purchaseOrder->delete();
        return response()->json(['message' => 'Purchase order archived.']);
    }

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);
        try {
            $po = $this->service->submit($purchaseOrder);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PurchaseOrderResource($po);
    }

    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        Gate::authorize('approve', $purchaseOrder);
        try {
            $po = $this->service->approve($purchaseOrder);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PurchaseOrderResource($po);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);

        $data = $request->validate([
            'items'         => 'required|array|min:1',
            'items.*.id'    => 'required|uuid',
            'items.*.qty'   => 'required|numeric|min:0.01',
            'notes'         => 'sometimes|nullable|string|max:1000',
        ]);

        $received = [];
        foreach ($data['items'] as $line) {
            $received[$line['id']] = (float) $line['qty'];
        }

        try {
            $po = $this->service->receive($purchaseOrder, $received, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new PurchaseOrderResource($po);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);

        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        try {
            $po = $this->service->cancel($purchaseOrder, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new PurchaseOrderResource($po);
    }
}
