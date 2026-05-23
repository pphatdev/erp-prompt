# Testing Strategy: Sales Module

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Orders and Customers must be strictly scoped to the `tenant_id`. |
| **P0** | **Transaction** | Order fulfillment (Invoice + Subscription + StockMovement) must be atomic. |
| **P0** | **Controller responses** | All `confirm`/`cancel`/`storeFromQuotation` actions must return `XxxResource` directly — never `response()->json(['data' => $resource->toArray(...)])`. |
| **P0** | **Tenant PK is handle** | `App\Models\Central\Tenant` must use `handle` as primary key. `$centralTenant->id` is null; `$centralTenant->getKey()` returns the handle. |
| **P0** | **Domains table** | `database/migrations/central/2024_01_01_000002_create_domains_table.php` must exist, FK must reference `tenants.handle`. |
| **P0** | **Password hashing** | `TenantDatabaseSeeder` and `TenantProvisioningService` must pass plaintext passwords — never `Hash::make()`. The `User` model's `hashed` cast hashes exactly once. |
| **P1** | **Auto-provision on customer create** | `POST /customers` with `customer_type=tenant` must return a `CustomerResource` with `provisionedTenantId` and `provisionedSubdomain` populated. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; uses `{{token}}`. |
| **P1** | **O2C Workflow** | Status transitions follow defined state machine. |
| **P1** | **Invoice → Subscription auto-activation** | Confirming an invoice with a linked `new` subscription must trigger `SubscriptionConfirmed` and domain provisioning. |
| **P1** | **Handle uniqueness** | `tenant_handle` unique at DB + validation layer; `GET /customers/check-handle` returns correct `available: bool`. |
| **P1** | **Password reset** | `POST /users/{user}/reset-password` must accept `password` + `password_confirmation`, update the stored hash correctly, and reject mismatched confirmation. |
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

### Auto-provision on tenant customer create (P1)
```php
it('provisions tenant immediately when customer_type is tenant', function () {
    $res = $this->postJson('/api/v1/customers', [
        'name'          => 'Acme Corp',
        'email'         => 'admin@acme.com',
        'customer_type' => 'tenant',
        'tenant_handle' => 'acme-corp',
    ])->assertStatus(201);

    expect($res->json('data.provisionedTenantId'))->toBe('acme-corp');
    expect($res->json('data.provisionedSubdomain'))->toContain('acme-corp');

    // Central tenant must exist with handle as PK
    $tenant = \App\Models\Central\Tenant::find('acme-corp');
    expect($tenant)->not->toBeNull();
    expect($tenant->getKey())->toBe('acme-corp');
});

it('saves customer even when provisioning fails', function () {
    // Mock TenantProvisioningService to throw
    $this->mock(TenantProvisioningService::class)
         ->shouldReceive('provisionForCustomer')->andThrow(new \RuntimeException('DB error'));

    $res = $this->postJson('/api/v1/customers', [
        'name'          => 'Fail Corp',
        'email'         => 'fail@corp.com',
        'customer_type' => 'tenant',
        'tenant_handle' => 'fail-corp',
    ])->assertStatus(201);

    // Customer saved, provisioning silently failed
    expect($res->json('data.provisionedTenantId'))->toBeNull();
    expect(Customer::where('email', 'fail@corp.com')->exists())->toBeTrue();
});
```

### Tenant model uses handle as PK (P0)
```php
it('tenant primary key is handle not uuid', function () {
    $tenant = \App\Models\Central\Tenant::create([
        'handle' => 'test-handle',
        'name'   => 'Test Tenant',
    ]);

    expect($tenant->getKey())->toBe('test-handle');
    expect($tenant->handle)->toBe('test-handle');
    // No id column
    expect(\Schema::hasColumn('tenants', 'id'))->toBeFalse();
});
```

### Password hashing contract (P0)
```php
it('seeded admin user can login with plaintext password', function () {
    // Run seeder inside tenant context
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\TenantDatabaseSeeder']);

    $user = \App\Models\Tenant\User::where('email', 'admin@example.com')->first();
    expect(\Illuminate\Support\Facades\Hash::check('password', $user->getAuthPassword()))->toBeTrue();
});
```

### Password reset (P1)
```php
it('admin can reset another user password', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($adminUser)
         ->postJson("/api/v1/users/{$user->id}/reset-password", [
             'password'              => 'new-secret-8',
             'password_confirmation' => 'new-secret-8',
         ])->assertOk();

    expect(Hash::check('new-secret-8', $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('rejects mismatched password confirmation', function () {
    $this->actingAs($adminUser)
         ->postJson("/api/v1/users/{$user->id}/reset-password", [
             'password'              => 'new-secret-8',
             'password_confirmation' => 'different',
         ])->assertStatus(422)
           ->assertJsonValidationErrors(['password']);
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
    $orderId = $res->json('data.orderId');
    expect($orderId)->toBeNull();
});
```

## 3. Postman Verification
- **Collection**: `postman.json`
- **Fast-path scenario**: `POST /customers` (type=tenant) → assert `provisionedTenantId` + `provisionedSubdomain` in response → login at `{handle}.localhost` with customer email / `password`.
- **O2C scenario**: Customer (tenant type) → Quote → Order → Invoice confirm → assert subscription confirmed + provisioned_tenant_id set.

## 4. Data Integrity
- `tenant_handle` must be `null` for `individual`/`business` customers, or a valid kebab-case string for `tenant` customers.
- `Customer.provisioned_tenant_id` stores the **handle** string (which is also the central tenant PK). Must match a row in `tenants.handle` after provisioning.
- `domains` table must have an entry `{handle}.{APP_SYSTEM_DOMAIN}` after provisioning, FK pointing to `tenants.handle`.
- Physical tenant database must be named `tenant_{handle}` (e.g. `tenant_kean`).
- Provisioned tenant must have a user with the customer's email, password `password`, and `admin` role.

## 5. Repair command (dev / existing tenants)
```bash
# Fix double-hashed passwords + create missing customer admin user
php artisan tenants:repair-credentials --tenant={handle}
```
