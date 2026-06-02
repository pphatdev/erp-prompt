<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomRefund;
use App\Tenants\Modules\Ecommerce\Resources\EcomRefundResource;
use App\Tenants\Modules\Ecommerce\Services\RefundService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceRefundController extends Controller
{
    use Paginates;

    public function __construct(private readonly RefundService $refunds)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EcomRefund::class);

        $query = EcomRefund::query()->with('items')->orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }

        return $this->paginatedResponse(EcomRefundResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(EcomRefund $refund): EcomRefundResource
    {
        $this->authorize('view', $refund);
        return new EcomRefundResource($refund->load(['items', 'order', 'payment']));
    }

    public function store(Request $request): EcomRefundResource|JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|exists:ecom_orders,id',
            'reason' => 'sometimes|nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:ecom_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.restock' => 'sometimes|boolean',
        ]);

        $order = EcomOrder::with('items')->findOrFail($data['order_id']);
        $this->authorize('create', EcomRefund::class);

        try {
            $refund = $this->refunds->request($order, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EcomRefundResource($refund->load(['items', 'order']));
    }

    public function approve(Request $request, EcomRefund $refund): EcomRefundResource|JsonResponse
    {
        $this->authorize('approve', $refund);
        $data = $request->validate([
            'provider_refund_id' => 'sometimes|nullable|string|max:120',
        ]);
        try {
            $refund = $this->refunds->approve($refund, $data['provider_refund_id'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomRefundResource($refund->load(['items', 'order', 'payment']));
    }

    public function reject(Request $request, EcomRefund $refund): EcomRefundResource|JsonResponse
    {
        $this->authorize('reject', $refund);
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        try {
            $refund = $this->refunds->reject($refund, $data['reason']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomRefundResource($refund->load('items'));
    }
}
