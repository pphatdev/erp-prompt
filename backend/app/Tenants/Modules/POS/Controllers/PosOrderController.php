<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosShift;
use App\Tenants\Modules\POS\Resources\PosOrderResource;
use App\Tenants\Modules\POS\Services\PosOrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosOrderController extends Controller
{
    use Paginates;

    public function __construct(private readonly PosOrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PosOrder::class);
        $query = PosOrder::query()
            ->with(['cashier', 'customer', 'items', 'payments'])
            ->orderByDesc('placed_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($shiftId = $request->query('shift_id')) {
            $query->where('shift_id', $shiftId);
        }
        if ($terminalId = $request->query('terminal_id')) {
            $query->where('terminal_id', $terminalId);
        }
        if ($cashierId = $request->query('cashier_id')) {
            $query->where('cashier_id', $cashierId);
        }
        return $this->paginatedResponse(PosOrderResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(PosOrder $order): PosOrderResource
    {
        $this->authorize('view', $order);
        return new PosOrderResource($order->load(['cashier', 'customer', 'items', 'payments']));
    }

    public function store(Request $request): PosOrderResource|JsonResponse
    {
        $this->authorize('create', PosOrder::class);
        $data = $request->validate([
            'shift_id' => 'required|exists:pos_shifts,id',
            'client_uuid' => 'sometimes|nullable|string|max:64',
            'customer_id' => 'sometimes|nullable|exists:customers,id',
            'notes' => 'sometimes|nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.discount' => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_amount' => 'sometimes|nullable|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:cash,card,wallet,manual',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.tendered' => 'sometimes|nullable|numeric|min:0',
            'payments.*.reference_number' => 'sometimes|nullable|string|max:120',
        ]);

        $shift = PosShift::findOrFail($data['shift_id']);
        try {
            $order = $this->orders->checkout($shift, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosOrderResource($order->load(['cashier', 'customer', 'items', 'payments']));
    }

    public function void(Request $request, PosOrder $order): PosOrderResource|JsonResponse
    {
        $this->authorize('void', $order);
        $data = $request->validate([
            'reason' => 'sometimes|nullable|string|max:500',
        ]);
        try {
            $order = $this->orders->voidOrder($order, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosOrderResource($order->load(['cashier', 'customer', 'items', 'payments']));
    }
}
