# Testing Strategy: Sales Module

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Orders and Customers must be strictly scoped to the `tenant_id`. |
| **P0** | **Transaction** | Order fulfillment (Invoice + Subscription + StockMovement) must be atomic. |
| **P0** | **Controller responses** | All `confirm`/`cancel`/`storeFromQuotation` actions must return `XxxResource` directly — never `response()->json(['data' => $resource->toArray(...)])`. |
| **P0** | **Domains table** | `database/migrations/central/2024_01_01_000002_create_domains_table.php` must exist and be migrated before provisioning runs. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; uses `{{token}}`. |
| **P1** | **O2C Workflow** | Status transitions follow defined state machine. |
| **P1** | **Invoice → Subscription auto-activation** | Confirming an invoice with a linked `new` subscription must trigger `SubscriptionConfirmed` and domain provisioning. |
| **P1** | **Handle uniqueness** | `tenant_handle` unique at DB + validation layer; `GET /customers/check-handle` returns correct `available: bool`. |
| **P2** | **Export** | Sales reports exportable via `sales.orders.export`. |

## 2. Backend Testing (Pest PHP)

### Tenancy Isolation (P0)
```php
it('cannot access another tenant\'s customer', function () {
    $other = Customer::factory()->create(['tenant_id' => 'tenant-b']);
    $this->getJson("/api/v1/customers/{$other->id}")->assertStatus(404);
});
```

### Atomic order fulfillment (P0)
```php
it('rolls back order on insufficient stock', function () {
    // Trigger StockService to throw, assert order stays new, invoice and subscription absent
});
```

### Invoice auto-confirms subscription (P1)
```php
it('confirms linked subscription when invoice is confirmed', function () {
    // Create confirmed order with software line → invoice new + subscription new
    $this->postJson("/api/v1/invoices/{$invoice->id}/confirm")->assertOk();
    expect($subscription->fresh()->status)->toBe('confirmed');
});
```

### SubscriptionConfirmed dispatches after commit (P1)
```php
it('dispatches SubscriptionConfirmed outside transaction', function () {
    Event::fake([SubscriptionConfirmed::class]);
    $this->postJson("/api/v1/subscriptions/{$sub->id}/confirm")->assertOk();
    Event::assertDispatched(SubscriptionConfirmed::class);
    // Subscription row must be committed before dispatch
    expect($sub->fresh()->status)->toBe('confirmed');
});
```

### Handle uniqueness (P1)
```php
it('rejects duplicate tenant_handle on create', function () {
    Customer::factory()->create(['tenant_handle' => 'acme-corp']);
    $this->postJson('/api/v1/customers', ['tenant_handle' => 'acme-corp', ...])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['tenant_handle']);
});

it('check-handle returns false for taken handle', function () {
    Customer::factory()->create(['tenant_handle' => 'taken']);
    $this->getJson('/api/v1/customers/check-handle?handle=taken')
         ->assertOk()
         ->assertJson(['available' => false]);
});

it('check-handle ignores self on edit', function () {
    $c = Customer::factory()->create(['tenant_handle' => 'mine']);
    $this->getJson("/api/v1/customers/check-handle?handle=mine&ignore_id={$c->id}")
         ->assertJson(['available' => true]);
});
```

### Controller response pipeline (P0)
```php
it('confirm returns valid orderId string not object', function () {
    $res = $this->postJson("/api/v1/quotations/{$quote->id}/confirm")->assertOk();
    // orderId must be absent (null / missing) or a valid UUID string — never {}
    $orderId = $res->json('data.orderId');
    expect($orderId)->toBeNull(); // no order created at confirm time
});
```

## 3. Postman Verification
- **Collection**: `postman.json`
- **Scenarios**: Full O2C: Customer (tenant type) → Quote → Order → Invoice confirm → assert subscription confirmed + provisioned_tenant_id set.

## 4. Data Integrity
- `tenant_handle` must be `null` for `individual`/`business` customers, or a valid kebab-case string for `tenant` customers.
- `Customer.provisioned_tenant_id` must match an entry in the central `tenants` table after provisioning.
- `domains` table must have an entry `{handle}.{APP_SYSTEM_DOMAIN}` after provisioning.
