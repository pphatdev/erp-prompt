<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomAddress;
use App\Models\Tenant\EcomCart;
use App\Models\Tenant\EcomOrder;
use App\Tenants\Modules\Ecommerce\Resources\EcomOrderResource;
use App\Tenants\Modules\Ecommerce\Resources\EcomPaymentResource;
use App\Tenants\Modules\Ecommerce\Services\CartService;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Ecommerce\Services\EcomCustomerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Storefront checkout. `initiate` mints the order + payment intent;
 * `confirm` is normally called by the gateway webhook, but a fallback
 * endpoint exists here so the SPA can self-confirm on success when running
 * against a sandbox provider that doesn't post webhooks back.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartService $carts,
        private readonly EcomCustomerService $customers,
    ) {
    }

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => 'required|string|max:64',
            'provider' => 'required|in:stripe,aba,wing,manual',
            'shipping_address_id' => 'sometimes|nullable|exists:ecom_addresses,id',
            'billing_address_id' => 'sometimes|nullable|exists:ecom_addresses,id',
            'session_token' => 'sometimes|nullable|string|max:120',
            'guest_email' => 'sometimes|nullable|email|max:255',
        ]);

        $cart = $this->resolveCart($request, $data['session_token'] ?? null);

        $shipping = !empty($data['shipping_address_id'])
            ? EcomAddress::findOrFail($data['shipping_address_id'])
            : null;
        $billing = !empty($data['billing_address_id'])
            ? EcomAddress::findOrFail($data['billing_address_id'])
            : null;

        $guest = null;
        if (!Auth::guard('shop')->check() && !empty($data['guest_email'])) {
            $guest = $this->customers->createGuest($data['guest_email']);
        }

        try {
            $result = $this->checkout->initiate(
                $cart,
                $data['client_uuid'],
                $data['provider'],
                $shipping,
                $billing,
                $guest,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => (new EcomOrderResource($result['order']))->toArray($request),
            'payment' => (new EcomPaymentResource($result['payment']))->toArray($request),
        ], 201);
    }

    /**
     * Sandbox-only fallback for SPAs without webhook delivery. Production
     * paths confirm via WebhookController. Storefronts should never call
     * this in real money flows.
     */
    public function confirmDirect(Request $request, EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $data = $request->validate([
            'charge_id' => 'required|string|max:120',
            'gateway_fee' => 'sometimes|numeric|min:0',
        ]);
        $this->assertOrderBelongsToActor($request, $order);

        try {
            $order = $this->checkout->confirm($order, [
                'charge_id' => $data['charge_id'],
                'gateway_fee' => $data['gateway_fee'] ?? 0,
                'source' => 'storefront_direct_confirm',
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EcomOrderResource($order->load(['items', 'payments']));
    }

    public function cancel(Request $request, EcomOrder $order): EcomOrderResource|JsonResponse
    {
        $data = $request->validate([
            'reason' => 'sometimes|nullable|string|max:500',
        ]);
        $this->assertOrderBelongsToActor($request, $order);

        try {
            $order = $this->checkout->cancel($order, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EcomOrderResource($order);
    }

    private function resolveCart(Request $request, ?string $sessionToken): EcomCart
    {
        $customer = Auth::guard('shop')->user();
        if ($customer) {
            return $this->carts->getOrCreateForCustomer($customer);
        }
        if (!$sessionToken) {
            $sessionToken = $request->header('X-Cart-Session');
        }
        if (!is_string($sessionToken) || $sessionToken === '') {
            abort(401, 'Provide X-Cart-Session header or log in.');
        }
        return $this->carts->getOrCreateForGuest($sessionToken);
    }

    private function assertOrderBelongsToActor(Request $request, EcomOrder $order): void
    {
        $customer = Auth::guard('shop')->user();
        if ($customer && $order->customer_id === $customer->id) {
            return;
        }
        $sessionToken = $request->header('X-Cart-Session');
        if ($order->cart && $order->cart->session_token === $sessionToken) {
            return;
        }
        abort(403, 'You do not own this order.');
    }
}
