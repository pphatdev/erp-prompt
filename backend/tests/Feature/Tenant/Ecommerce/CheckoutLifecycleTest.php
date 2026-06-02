<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Ecommerce;

use App\Models\Tenant\EcomCart;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Ecommerce\Services\CartService;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

/**
 * P1 — Full storefront happy path:
 *   add to cart -> stock reserved -> initiate -> confirm
 *     -> stock decremented (commit posts an out-movement)
 *     -> EcomOrder marked paid + linked to Sales Invoice
 *     -> Invoice confirmed and journal_entry_id populated
 */
class CheckoutLifecycleTest extends TenantTestCase
{
    private CartService $cartService;
    private CheckoutService $checkoutService;
    private StockService $stock;
    private Warehouse $warehouse;
    private Product $product;
    private EcomCustomer $shopper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
        $this->checkoutService = app(CheckoutService::class);
        $this->stock = app(StockService::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-ECOM', 'name' => 'Ecom WH']);
        // Pin the default warehouse so the cart's reservation can resolve it
        // without depending on Warehouse::count() === 1.
        Setting::create([
            'key' => 'inventory.default_warehouse_code',
            'value' => 'WH-ECOM',
            'group' => 'inventory',
            'type' => 'string',
        ]);

        $this->product = Product::create([
            'sku' => 'ECO-1',
            'name' => 'Test Product',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 25.00,
            'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_cost' => 10.00,
        ]);

        $this->shopper = EcomCustomer::create([
            'email' => 'shopper@test.com',
            'password' => 'secret123',
            'first_name' => 'Buyer',
        ]);
    }

    public function test_full_storefront_lifecycle_posts_invoice_and_decrements_stock(): void
    {
        // 1. Add item to cart — reserves stock.
        $cart = $this->cartService->getOrCreateForCustomer($this->shopper);
        $this->cartService->addItem($cart, [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
        $cart->refresh();
        $this->assertEquals(50.00, (float) $cart->subtotal);
        $this->assertSame(8.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id),
            'Adding 2 to cart must drop net available from 10 to 8.');
        $this->assertSame(10.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id),
            'Physical stock must remain untouched until checkout confirms.');

        // 2. Initiate checkout.
        $clientUuid = 'unique-uuid-1';
        $result = $this->checkoutService->initiate($cart->fresh('items'), $clientUuid, 'manual', null, null);
        /** @var EcomOrder $order */
        $order = $result['order'];
        /** @var EcomPayment $payment */
        $payment = $result['payment'];

        $this->assertSame(EcomOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertEquals(50.00, (float) $order->total_amount);
        $this->assertSame('manual', $payment->provider);
        $this->assertSame(EcomPayment::STATUS_PENDING, $payment->status);

        // 3. Confirm checkout.
        $confirmed = $this->checkoutService->confirm($order, [
            'charge_id' => 'ch_test_123',
            'gateway_fee' => 1.50,
        ]);

        $this->assertSame(EcomOrder::STATUS_PAID, $confirmed->status);
        $this->assertNotNull($confirmed->invoice_id);
        $this->assertNotNull($confirmed->sales_order_id);

        // 4. Stock now physically decremented.
        $this->assertSame(8.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));
        $this->assertSame(StockReservation::STATUS_COMMITTED,
            StockReservation::where('reference', "CART:{$cart->id}")->first()?->status);

        // 5. Cart archived.
        $this->assertSame(EcomCart::STATUS_CONVERTED, $cart->fresh()->status);

        // 6. Sales Invoice confirmed and AR journal posted.
        $invoice = Invoice::find($confirmed->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::STATUS_CONFIRMED, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);

        // 7. Payment captured with gateway fee recorded.
        $payment->refresh();
        $this->assertSame(EcomPayment::STATUS_SUCCEEDED, $payment->status);
        $this->assertEquals(1.50, (float) $payment->gateway_fee);
        $this->assertSame('ch_test_123', $payment->provider_charge_id);
    }
}
