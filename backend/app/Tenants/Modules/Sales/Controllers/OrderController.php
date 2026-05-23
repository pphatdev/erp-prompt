<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\Quotation;
use App\Tenants\Modules\Sales\Resources\OrderResource;
use App\Tenants\Modules\Sales\Services\OrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use Paginates;

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['customer', 'items', 'invoice', 'subscription'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(OrderResource::class, $paginator, $request);
    }

    /**
     * Ad-hoc Order (no upstream quote). Hybrid sales flow prefers
     * `storeFromQuotation` but this stays for backward-compat and dev tools.
     */
    public function store(Request $request): OrderResource
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'due_date' => 'sometimes|nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.due_date' => 'sometimes|nullable|date',
            'items.*.notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $order = $this->orders->createOrder($data);

        return new OrderResource($order->load(['customer', 'items']));
    }

    /**
     * Canonical hybrid-sales entry point: convert a confirmed Quotation to
     * a Sales Order. POST /api/v1/quotations/{quotation}/convert-to-order
     */
    public function storeFromQuotation(Quotation $quotation): JsonResponse
    {
        try {
            $order = $this->orders->createFromQuotation($quotation);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new OrderResource($order->load(['customer', 'items'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['customer', 'items', 'invoice', 'subscription']));
    }

    public function confirm(Order $order): OrderResource|JsonResponse
    {
        try {
            $this->orders->confirmOrder($order);
        } catch (DomainException | \Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OrderResource($order->fresh(['customer', 'items', 'invoice', 'subscription']));
    }

    public function cancel(Request $request, Order $order): OrderResource|JsonResponse
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        try {
            $this->orders->cancelOrder($order, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OrderResource($order->fresh(['customer', 'items', 'invoice', 'subscription']));
    }
}
