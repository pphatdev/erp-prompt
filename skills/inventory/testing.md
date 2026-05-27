# Testing Strategy: Inventory Management & SCM

All inventory features must be verified across multiple levels of the testing suite to enforce transactional safety, database isolation, and contract integrity.

---

## 1. Quality & Priority Matrix (P0 - P2)

| Priority | Category | Requirement / Test Case Description | Verified By |
| :--- | :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Verify Warehouse and Stock levels are strictly partitioned; Tenant A can never query or manipulate Tenant B's data. | Pest PHP |
| **P0** | **Transactional Safety** | Ensure inter-warehouse transfers are atomic; a failure on either side rolls back the full transaction (preventing double entries or loss of stock). | Pest PHP |
| **P0** | **eCommerce Reservation** | Verify stock reservations decrement available eCommerce quantities but don't permanently alter main warehouse counts until committed. | Pest PHP |
| **P1** | **Inventory Valuation** | Assert that WAC (Weighted Average Cost) and FIFO math perform precisely on Goods Receipts (GRN) and update core pricing. | Pest PHP |
| **P1** | **Concurrency Guard** | Assert that concurrent stock-out requests on the same product are locked and safely queued, avoiding negative stock levels. | Pest PHP / Concurrency Test |
| **P1** | **Catalog Pricing SSOT** | Verify Quotations, CRM product schedules, and eCommerce cart checkouts dynamically pull exact pricing from the central SCM product list. | Pest PHP |
| **P1** | **Price Override Security** | Assert that manual pricing overrides in Sales and Quotation builders fail unless the active user has the `sales.price.override` permission. | Pest PHP |
| **P1** | **Threshold Alerts** | Verify that a stock movement bringing levels below `minimum_stock_level` dispatches a `LowStockAlert` event/notification. | Pest PHP / Event Assertion |
| **P1** | **Reservation Expiry** | Assert that background sweep daemons successfully clear expired eCom checkout reservations and restore Net Available Stock. | Pest PHP |
| **P2** | **Procurement Flow** | Verify that updating a Purchase Order updates corresponding status badges and triggers a matching GRN option. | Playwright / Vitest |

---

## 2. Backend Testing Patterns (Pest PHP)

### A. Tenancy Isolation Test (P0)
```php
it('enforces strict multi-tenant isolation for stock levels and warehouses', function () {
    $tenantA = Tenant::factory()->create(['handle' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['handle' => 'tenant-b']);

    // Act as Tenant A
    tenancy()->initialize($tenantA);
    $warehouseA = Warehouse::create([
        'code' => 'WH-A',
        'name' => 'Main Warehouse A',
        'tenant_id' => 'tenant-a'
    ]);
    
    // Act as Tenant B
    tenancy()->initialize($tenantB);
    $warehouseB = Warehouse::create([
        'code' => 'WH-B',
        'name' => 'Main Warehouse B',
        'tenant_id' => 'tenant-b'
    ]);

    // Assert Tenant A cannot see Tenant B's warehouse
    tenancy()->initialize($tenantA);
    expect(Warehouse::where('id', $warehouseB->id)->exists())->toBeFalse();
    
    // Assert Tenant B cannot see Tenant A's warehouse
    tenancy()->initialize($tenantB);
    expect(Warehouse::where('id', $warehouseA->id)->exists())->toBeFalse();
});
```

### B. Transactional Transfer Safety (P0)
```php
it('rolls back the entire transfer if the target warehouse increment fails', function () {
    $product = Product::factory()->create(['sku' => 'SKU-TEST', 'minimum_stock_level' => 5]);
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    // Setup initial stock at origin (10 items)
    $stockService = app(StockService::class);
    $stockService->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseA->id,
        'type' => 'in',
        'quantity' => 10,
    ]);

    // Force an exception during transfer (simulate DB connection issue or validation failure)
    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new Exception('Simulated destination failure'));

    try {
        $stockService->transferStock($product->id, $warehouseA->id, $warehouseB->id, 5);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Simulated destination failure');
    }

    // Verify stock at Warehouse A is still intact (10 items)
    $stockA = StockMovement::where('product_id', $product->id)
        ->where('warehouse_id', $warehouseA->id)
        ->sum('quantity');

    expect($stockA)->toBe(10);
});
```

### C. Low-Stock Notification Assertion (P1)
```php
it('triggers a LowStockAlert notification when inventory crosses minimum threshold', function () {
    Notification::fake();

    $product = Product::factory()->create(['sku' => 'SKU-LIGHT', 'minimum_stock_level' => 10]);
    $warehouse = Warehouse::factory()->create();
    $stockService = app(StockService::class);

    // Initial stock: 15 items
    $stockService->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'in',
        'quantity' => 15,
    ]);

    // Draw down 7 items, leaving 8 (crosses minimum threshold of 10)
    $stockService->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'out',
        'quantity' => 7,
    ]);

    // Assert notification sent to procurement managers
    Notification::assertSentTo(
        [$userWithProcurementPermission],
        LowStockAlertNotification::class
    );
});
```

### D. eCommerce Stock Reservation & TTL Release Test (P0)
```php
it('correctly reserves stock during eCom checkout and releases it if expired', function () {
    $product = Product::factory()->create(['sku' => 'ECOM-SKU']);
    $warehouse = Warehouse::factory()->create();
    $stockService = app(StockService::class);

    // Initial stock: 20 units
    $stockService->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'in',
        'quantity' => 20,
    ]);

    // Reserve 5 units for eCommerce Cart "cart-999"
    $reservation = StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'reserve',
        'quantity' => -5,
        'reference' => 'ecom-reserve-cart-999',
        'created_at' => now(),
    ]);

    // Query net available stock (must be 15, despite physical stock technically being 20)
    $netAvailable = $stockService->getNetAvailableStock($product->id);
    expect($netAvailable)->toBe(15);

    // Act: Simulate time passage (20 minutes) and execute background daemon sweep
    $reservation->update(['created_at' => now()->subMinutes(20)]);
    app(StockReservationDaemon::class)->sweepExpiredReservations();

    // Assert: The reservation is cleared and net stock is restored to 20
    expect(StockMovement::where('id', $reservation->id)->exists())->toBeFalse();
    expect($stockService->getNetAvailableStock($product->id))->toBe(20);
});
```

### E. Catalog Sourced Pricing & Override Enforcement Test (P1)
```php
it('auto-populates quotation price from central product catalog and locks overrides without IAM permission', function () {
    $product = Product::factory()->create([
        'sku' => 'SSOT-PRICE-SKU',
        'unit_price' => 299.99
    ]);

    $userWithoutOverridePerm = User::factory()->create();
    $userWithOverridePerm = User::factory()->create();
    $userWithOverridePerm->grantPermission('sales.price.override');

    // 1. Act: Create Quotation without override permission
    $this->actingAs($userWithoutOverridePerm, 'api')
        ->postJson('/api/v1/quotations', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.items.0.price', '299.99'); // Sourced directly from catalog

    // 2. Act: Try to override price to 150.00 without override permission (must fail override and fallback/error)
    $this->actingAs($userWithoutOverridePerm, 'api')
        ->postJson('/api/v1/quotations', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 150.00]
            ]
        ])
        ->assertStatus(403); // Forbidden override

    // 3. Act: Successfully override price to 150.00 WITH override permission
    $this->actingAs($userWithOverridePerm, 'api')
        ->postJson('/api/v1/quotations', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 150.00]
            ]
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.items.0.price', '150.00'); // Override accepted
});
```

---

## 3. API Contract & E2E Testing (Playwright / Vitest)

### A. Procurement 3-Way Match Check (P1)
1.  **Mock API**: Intercept `/api/v1/invoices` and `/api/v1/orders` payloads.
2.  **Vitest Scenario**: Render `PurchaseOrderPanel` and mock receiving `partially_received` PO payload.
3.  **UI Assertion**:
    *   Verify quantity balance checks are visible.
    *   Assert "Generate GRN" button is active.
    *   Verify warning badges display when Invoice amount diverges from the original PO valuation.
