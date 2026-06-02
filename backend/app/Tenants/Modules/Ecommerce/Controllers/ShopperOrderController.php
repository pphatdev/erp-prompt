<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomOrder;
use App\Tenants\Modules\Ecommerce\Resources\EcomOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shopper's own-order index/detail. Always scoped to the authenticated
 * EcomCustomer — admin-side listings live in EcommerceOrderController.
 */
class ShopperOrderController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $customer = Auth::guard('shop')->user();
        $query = EcomOrder::query()
            ->where('customer_id', $customer->id)
            ->with(['items', 'payments'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginatedResponse(EcomOrderResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(Request $request, EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $customer = Auth::guard('shop')->user();
        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return new EcomOrderResource($order->load(['items', 'payments', 'refunds.items']));
    }
}
