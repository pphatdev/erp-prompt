# Testing Strategy: eCommerce Module

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Shoppers, carts, orders, and payment logs must be strictly isolated to `tenant_id`. Tenant A must never be able to access Tenant B's B2C orders or shopping accounts. |
| **P0** | **Transaction Integrity** | Processing checkouts (deducting stock, generating invoicing journal entries, writing payments log) must run under an atomic transaction; any sub-failure rolls back database adjustments. |
| **P0** | **Webhook Security** | All callback webhook payloads must validate gateway provider signatures. Tampered or fake webhook requests must be blocked with `400 Bad Request`. |
| **P1** | **Checkout Idempotency** | Invocations with matching `client_uuid` must return original checkout states, strictly preventing double billing or double stock deductions. |
| **P1** | **Stock Reservation TTL**| Expired carts must yield their variants back to active inventory via standard 15-minute cron tasks. |
| **P1** | **FSM Restrictions** | Moving orders out of final statuses like `cancelled` or `refunded` must throw `422 Unprocessable Content` errors. |
| **P2** | **Exchange Rates Integration**| Base settings currencies (e.g. converting KHR to USD) must align mathematically inside payment logs. |

---

## 2. Backend Testing (Pest PHP Templates)

### Tenancy Isolation (P0)
```php
<?php

use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomCustomer;

it('ensures Tenant A cannot access Tenant B B2C order records', function () {
    // 1. Setup Tenant A context
    tenancy()->initialize($this->tenantA);
    $customerA = EcomCustomer::factory()->create();
    $orderA = EcomOrder::factory()->create([
        'ecom_customer_id' => $customerA->id,
        'order_number' => 'ECOO-8888',
        'total_amount' => 150.00,
    ]);

    // 2. Setup Tenant B context and construct a record
    tenancy()->initialize($this->tenantB);
    $customerB = EcomCustomer::factory()->create();
    $orderB = EcomOrder::factory()->create([
        'ecom_customer_id' => $customerB->id,
        'order_number' => 'ECOO-9999',
        'total_amount' => 200.00,
    ]);

    // 3. Authenticate as Tenant A shopper and try to fetch Tenant B's order
    tenancy()->initialize($this->tenantA);
    $this->actingAs($customerA, 'shopper-api');

    $this->getJson("/api/v1/shop/orders/{$orderB->id}")
        ->assertStatus(404); // Scoped global query guarantees it resolves as Not Found
});
```

### Atomic Checkout Safety (P0)
```php
<?php

use App\Models\Tenant\EcomOrder;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Sales\Services\InvoiceService;

it('rolls back stock reductions and payments if invoice generation crashes', function () {
    $order = EcomOrder::factory()->create([
        'status' => 'pending_payment',
        'total_amount' => 100.00
    ]);

    // Mock the downstream Sales module Invoice Service to fail
    $this->mock(InvoiceService::class)
        ->shouldReceive('createFromOrder')
        ->andThrow(new \RuntimeException('AR Posting Database Connection Failure'));

    try {
        app(CheckoutService::class)->confirm($order, 'stripe_txn_9999', ['metadata' => 'demo']);
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('AR Posting Database Connection Failure');
    }

    // Assert that the order is STILL in pending state
    expect($order->fresh()->status)->toBe('pending_payment');
    
    // Assert payment log was rolled back
    $this->assertDatabaseMissing('ecom_payments', [
        'provider_transaction_id' => 'stripe_txn_9999',
    ]);
});
```

### Idempotent Billing (P1)
```php
<?php

use App\Models\Tenant\EcomCart;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;

it('returns the same order status on duplicate client_uuid checkout submissions', function () {
    $cart = EcomCart::factory()->create();
    $clientUuid = 'unique-checkout-uuid-1234';

    $checkoutService = app(CheckoutService::class);

    // First attempt creates the order
    $order1 = $checkoutService->initiate($cart, $clientUuid);

    // Second attempt returns the existing order
    $order2 = $checkoutService->initiate($cart, $clientUuid);

    expect($order1->id)->toBe($order2->id);
    expect(EcomOrder::where('order_number', $order1->order_number)->count())->toBe(1);
});
```

### Stock Reservation TTL Release (P1)
```php
<?php

use App\Models\Tenant\EcomCart;
use App\Models\Tenant\ProductVariant;
use App\Tenants\Modules\Inventory\Services\InventoryService;

it('releases stock reservation to active pool after 15 minutes cart expiry', function () {
    $variant = ProductVariant::factory()->create(['on_hand' => 10]);
    $cart = EcomCart::factory()->create();

    // Enforce 15-minute lock
    app(InventoryService::class)->reserve($variant->id, 2);
    expect(app(InventoryService::class)->getAvailableStock($variant->id))->toBe(8);

    // Travel time forward by 16 minutes
    this->travel(16)->minutes();

    // Trigger scheduled reservation daemon
    artisan('ecom:release-expired-reservations');

    // Available stock should restore to its original volume
    expect(app(InventoryService::class)->getAvailableStock($variant->id))->toBe(10);
});
```

---

## 3. Frontend E2E / Vitest Verification (Nuxt)

* **Independent Auth Context**: Ensure storefront pages store tokens under `shop_auth_token` to prevent overriding administrative credentials stored in `auth_token`.
* **Reservation Shimmer/Ticker**: Cart page (`pages/shop/cart.vue`) should read the reservation expiry timestamp and show a live countdown timer ticking down to 0, warning shoppers that items will be unlocked soon.
* **Checkout Button Disable Guard**: Verify that clicking the "Place Order" button triggers a disabled/loading spinner immediately to prevent double click submissions.
