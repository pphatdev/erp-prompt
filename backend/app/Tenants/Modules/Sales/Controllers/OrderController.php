<?php

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Tenants\Modules\Sales\Resources\OrderResource;
use App\Tenants\Modules\Sales\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use Paginates;

    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            Order::query()->with('customer', 'items')->orderBy('created_at', 'desc'),
            $request
        );

        return $this->paginatedResponse(OrderResource::class, $paginator, $request);
    }

    public function store(Request $request): OrderResource
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $order = $this->orderService->createOrder($data);
        return new OrderResource($order->load('customer', 'items'));
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('customer', 'items'));
    }

    public function confirm(Order $order): OrderResource
    {
        $this->orderService->confirmOrder($order);
        return new OrderResource($order->load('customer', 'items'));
    }
}
