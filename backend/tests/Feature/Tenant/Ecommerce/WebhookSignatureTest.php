<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Ecommerce;

use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Ecommerce\Services\CartService;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

/**
 * P0 — Webhook signature verification must reject tampered payloads and
 * accept correctly-signed ones. Replay (same event id / charge id) must
 * be idempotent: a second delivery with the same charge id is a no-op.
 */
class WebhookSignatureTest extends TenantTestCase
{
    private const STRIPE_SECRET = 'whsec_test_super_secret';

    private CartService $carts;
    private CheckoutService $checkout;
    private string $clientUuid;
    private EcomPayment $payment;
    private EcomOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carts = app(CartService::class);
        $this->checkout = app(CheckoutService::class);

        Setting::create([
            'key' => 'ecommerce.payment.stripe.webhook_secret',
            'value' => self::STRIPE_SECRET,
            'group' => 'ecommerce',
            'type' => 'string',
        ]);

        // Build a checkout in pending_payment so the webhook has something
        // to confirm against.
        $warehouse = Warehouse::create(['code' => 'WH-WHK', 'name' => 'Webhook WH']);
        Setting::create([
            'key' => 'inventory.default_warehouse_code',
            'value' => 'WH-WHK',
            'group' => 'inventory',
            'type' => 'string',
        ]);
        $product = Product::create([
            'sku' => 'WHK-1', 'name' => 'Item',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 20, 'minimum_stock_level' => 0,
        ]);
        app(StockService::class)->recordMovement([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'type' => 'in', 'quantity' => 5, 'unit_cost' => 10,
        ]);

        $shopper = EcomCustomer::create(['email' => 'w@test.com', 'password' => 'secret123']);
        $cart = $this->carts->getOrCreateForCustomer($shopper);
        $this->carts->addItem($cart, ['product_id' => $product->id, 'quantity' => 1]);

        $this->clientUuid = 'webhook-uuid-1';
        $result = $this->checkout->initiate($cart->fresh('items'), $this->clientUuid, 'stripe', null, null);
        $this->order = $result['order'];
        $this->payment = $result['payment'];
    }

    private function stripePayload(string $chargeId): array
    {
        return [
            'id' => 'evt_' . bin2hex(random_bytes(6)),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'metadata' => ['client_uuid' => $this->clientUuid],
                ],
            ],
        ];
    }

    private function sign(string $rawBody): array
    {
        $ts = (string) time();
        $sig = hash_hmac('sha256', "{$ts}.{$rawBody}", self::STRIPE_SECRET);
        return ['t' => $ts, 'v1' => $sig];
    }

    public function test_tampered_payload_is_rejected_with_401(): void
    {
        $payload = $this->stripePayload('ch_test_aaa');
        $rawBody = json_encode($payload);
        $parts = $this->sign($rawBody);

        // Tamper with the body AFTER signing — sig no longer matches.
        $tampered = str_replace('ch_test_aaa', 'ch_test_BBB', $rawBody);

        $response = $this->withHeaders([
            'X-Tenant-Handle' => 'test',
            'Stripe-Signature' => "t={$parts['t']},v1={$parts['v1']}",
            'Content-Type' => 'application/json',
        ])->call('POST', '/api/v1/ecom/webhooks/stripe', [], [], [], [], $tampered);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'invalid_signature');

        // Payment is still pending — no confirm fired.
        $this->assertSame(EcomPayment::STATUS_PENDING, $this->payment->fresh()->status);
    }

    public function test_valid_signature_confirms_order_and_replay_is_idempotent(): void
    {
        $payload = $this->stripePayload('ch_test_real_123');
        $rawBody = json_encode($payload);
        $parts = $this->sign($rawBody);
        $header = "t={$parts['t']},v1={$parts['v1']}";

        $first = $this->withHeaders([
            'X-Tenant-Handle' => 'test',
            'Stripe-Signature' => $header,
            'Content-Type' => 'application/json',
        ])->call('POST', '/api/v1/ecom/webhooks/stripe', [], [], [], [], $rawBody);

        $first->assertStatus(200);
        $first->assertJsonPath('accepted', true);

        $this->order->refresh();
        $this->assertSame(EcomOrder::STATUS_PAID, $this->order->status);
        $this->payment->refresh();
        $this->assertSame(EcomPayment::STATUS_SUCCEEDED, $this->payment->status);
        $this->assertSame('ch_test_real_123', $this->payment->provider_charge_id);

        // Replay the SAME webhook — must be a no-op.
        $second = $this->withHeaders([
            'X-Tenant-Handle' => 'test',
            'Stripe-Signature' => $header,
            'Content-Type' => 'application/json',
        ])->call('POST', '/api/v1/ecom/webhooks/stripe', [], [], [], [], $rawBody);

        $second->assertStatus(200);
        $second->assertJsonPath('note', 'duplicate_event');
    }

    public function test_webhook_without_configured_secret_returns_503(): void
    {
        Setting::where('key', 'ecommerce.payment.stripe.webhook_secret')->delete();

        $response = $this->withHeaders([
            'X-Tenant-Handle' => 'test',
            'Stripe-Signature' => 't=1,v1=anything',
            'Content-Type' => 'application/json',
        ])->call('POST', '/api/v1/ecom/webhooks/stripe', [], [], [], [], '{}');

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'webhook_not_configured');
    }
}
