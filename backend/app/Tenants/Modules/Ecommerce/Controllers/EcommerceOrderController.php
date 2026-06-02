<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomOrder;
use App\Tenants\Modules\Ecommerce\Resources\EcomOrderResource;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Ecommerce\Services\FulfillmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-side ecommerce order management. Permission-gated via
 * EcomOrderPolicy (`ecommerce.orders.*`).
 */
class EcommerceOrderController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly FulfillmentService $fulfillment,
        private readonly CheckoutService $checkout,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EcomOrder::class);

        $query = EcomOrder::query()
            ->with(['customer', 'items', 'payments'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($search = $request->query('search')) {
            $query->where('order_number', 'ilike', "%{$search}%");
        }

        return $this->paginatedResponse(EcomOrderResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(EcomOrder $order): EcomOrderResource
    {
        $this->authorize('view', $order);
        return new EcomOrderResource($order->load(['customer', 'items', 'payments', 'refunds.items', 'invoice']));
    }

    public function markFulfilling(EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $this->authorize('update', $order);
        try {
            $order = $this->fulfillment->markFulfilling($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomOrderResource($order->load(['items', 'payments']));
    }

    public function ship(Request $request, EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $this->authorize('update', $order);
        $data = $request->validate([
            'carrier' => 'required|string|max:60',
            'tracking_number' => 'required|string|max:120',
        ]);
        try {
            $order = $this->fulfillment->ship($order, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomOrderResource($order->load(['items', 'payments']));
    }

    public function markDelivered(EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $this->authorize('update', $order);
        try {
            $order = $this->fulfillment->markDelivered($order);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomOrderResource($order->load(['items', 'payments']));
    }

    public function cancel(Request $request, EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $this->authorize('cancel', $order);
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);
        try {
            $order = $this->checkout->cancel($order, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new EcomOrderResource($order);
    }
}
