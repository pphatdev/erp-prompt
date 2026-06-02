<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Ecommerce;

use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Ecommerce\Services\CartService;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

/**
 * P1 — Storefront SPAs retry checkout calls all the time (network blips,
 * back-button, double-click). CheckoutService::initiate must dedupe on
 * (tenant_id, client_uuid) so the shopper never gets two orders or two
 * pending payments for the same submission.
 */
class IdempotentCheckoutTest extends TenantTestCase
{
    private CartService $cartService;
    private CheckoutService $checkoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = app(CartService::class);
        $this->checkoutService = app(CheckoutService::class);

        $warehouse = Warehouse::create(['code' => 'WH-IDEM', 'name' => 'Idem WH']);
        Setting::create([
            'key' => 'inventory.default_warehouse_code',
            'value' => 'WH-IDEM',
            'group' => 'inventory',
            'type' => 'string',
        ]);
        $product = Product::create([
            'sku' => 'IDEM-1',
            'name' => 'Idem Product',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10,
            'minimum_stock_level' => 0,
        ]);
        app(StockService::class)->recordMovement([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 50,
            'unit_cost' => 5,
        ]);

        $shopper = EcomCustomer::create([
            'email' => 'idem@test.com',
            'password' => 'secret123',
        ]);
        $cart = $this->cartService->getOrCreateForCustomer($shopper);
        $this->cartService->addItem($cart, ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_duplicate_client_uuid_returns_existing_order_and_payment(): void
    {
        $shopper = EcomCustomer::first();
        $cart = $shopper->carts()->first();

        $first = $this->checkoutService->initiate($cart->fresh('items'), 'shared-uuid', 'manual', null, null);
        $second = $this->checkoutService->initiate($cart->fresh('items'), 'shared-uuid', 'manual', null, null);

        $this->assertSame($first['order']->id, $second['order']->id, 'Same client_uuid must return the same EcomOrder.');
        $this->assertSame($first['payment']->id, $second['payment']->id, 'Same client_uuid must return the same EcomPayment.');

        // Only one payment row exists for that uuid (DB unique constraint backs it).
        $this->assertSame(1, EcomPayment::where('client_uuid', 'shared-uuid')->count());
    }
}
