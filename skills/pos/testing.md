# Testing Strategy: Point of Sale (POS)

This document outlines the testing priority matrix, backend Pest test implementations, frontend E2E and visual assertions, and API integration scenarios for the Point of Sale module.

---

## 1. Priority Matrix (P0 - P2)

Testing must cover security, calculation accuracy, state machines, and real-time operations, prioritizing tenancy isolation and double-entry accuracy.

| Priority | Category | Requirement / Test Case | Focus Area |
|---|---|---|---|
| **P0** | **Tenancy Isolation** | POS data (terminals, shifts, orders, payments) is strictly isolated by `tenant_id`. Access across tenants must throw `404 Not Found`. | DB Connection Isolation |
| **P0** | **Double-Entry Ledger** | All checkout operations post fully balanced debits and credits via `postEntry()`. Variance is $0.00$. Invalid values trigger transaction rollbacks. | cost accounting |
| **P1** | **Shift Cash Controls** | Reconciling closed shifts calculates exact cash drawer variances. Non-zero variances block cashiers from opening new shifts until supervisor override. | Security & Operations |
| **P1** | **Offline Sync Dedupe** | Syncing offline orders with pre-existing `client_uuid` values handles the request idempotently, avoiding double stock-out or double-ledger hits. | Resiliency & Syncing |
| **P1** | **Inventory Deductions** | Sales checkout instantly decrements inventory quantity inside the register terminal's associated warehouse using correct WAC cost deductions. | Multi-Module Integrations |
| **P2** | **Touch Interface Cart** | Cart calculates local taxes, quick keys, and discounts correctly. Dragging and parking carts preserves multi-cart IndexedDB states. | UI & UX Experience |
| **P2** | **Thermal Receipt Layout** | Checkout triggers thermal print output window. Elements hide standard layout navigation chrome, keeping receipt within 80mm printable margins. | Print Integrations |

---

## 2. Backend Testing (Pest PHP)

Tests run exclusively on the `erp_system_test` database connection (enforced by `phpunit.xml`). Seeders must run to establish workflow states.

### A. Data Isolation Test (P0)
This test asserts that a cashier request under Tenant A's connection context attempting to open or read Tenant B's active shift is met with a clean `404 Not Found`.

```php
<?php

use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Laravel\Passport\Passport;

uses(Tests\TestCase::class)->in(__DIR__);

test('cashier cannot read or access shifts from a different tenant', function () {
    // 1. Establish Tenant A & Tenant B
    $tenantA = createTenant('tenant-a');
    $tenantB = createTenant('tenant-b');

    // 2. Create Terminal and Active Shift in Tenant B
    tenancy()->initialize($tenantB);
    $terminalB = PosTerminal::create([
        'name' => 'Main Registry Terminal B',
        'status' => 'active',
        'tenant_id' => 'tenant-b',
    ]);
    $shiftB = PosShift::create([
        'terminal_id' => $terminalB->id,
        'opened_at' => now(),
        'opening_float' => 150.00,
        'status' => 'open',
        'tenant_id' => 'tenant-b',
    ]);
    tenancy()->end();

    // 3. Authenticate Cashier under Tenant A
    tenancy()->initialize($tenantA);
    $userA = User::factory()->create();
    $employeeA = Employee::factory()->create(['user_id' => $userA->id]);
    $userA->assignRole('cashier'); // Has pos.shift.read permission
    
    Passport::actingAs($userA);

    // 4. Request Tenant B's Shift via Tenant A's API connection
    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'tenant-a',
    ])->getJson("/api/v1/pos/shifts/{$shiftB->id}");

    // 5. Assert isolation hides the shift and returns a 404
    $response->assertStatus(404);
});
```

### B. Shift Reconciliation & Variance Block Test (P1)
This test verifies that a cashier shift closed with an imbalanced cash count is flagged as `variance_pending`, blocking the cashier from starting new sessions.

```php
test('shift closing count variance blocks opening new sessions until override', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);

    $cashier = Employee::factory()->create();
    $terminal = PosTerminal::create([
        'name' => 'Register 1',
        'tenant_id' => 'demo-tenant',
    ]);

    // Create an active shift
    $shift = PosShift::create([
        'terminal_id' => $terminal->id,
        'cashier_id' => $cashier->id,
        'opened_at' => now(),
        'opening_float' => 100.00,
        'status' => 'open',
        'tenant_id' => 'demo-tenant',
    ]);

    // Close shift with a discrepancy (Expected Cash: $100. Counted: $95. Variance: -$5)
    $user = User::factory()->create(['employee_id' => $cashier->id]);
    Passport::actingAs($user);

    $closeResponse = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/pos/shifts/{$shift->id}/close", [
        'closing_cash' => 95.00,
    ]);

    // 1. Assert shift transitions to variance pending
    $closeResponse->assertStatus(200);
    expect($closeResponse->json('data.status'))->toBe('variance_pending');
    expect($closeResponse->json('data.variance'))->toBe(-5.00);

    // 2. Attempt to open a brand new shift (Expected to fail)
    $openResponse = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/pos/shifts/open", [
        'terminal_id' => $terminal->id,
        'opening_float' => 100.00,
    ]);

    $openResponse->assertStatus(422);
    expect($openResponse->json('message'))->toContain('variance');
});
```

### C. Offline Sync Deduplication Test (P1)
This test asserts that submitting the exact same client-side order UUID multiple times returns `200 OK` safely, performing stock-outs and ledger postings only once.

```php
test('offline sync process is idempotent against client UUID matches', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);

    $terminal = PosTerminal::create(['name' => 'POS 1', 'tenant_id' => 'demo-tenant']);
    $shift = PosShift::create([
        'terminal_id' => $terminal->id,
        'opened_at' => now(),
        'opening_float' => 100.00,
        'status' => 'open',
        'tenant_id' => 'demo-tenant',
    ]);

    $clientUuid = (string) Str::uuid();

    $user = User::factory()->create();
    Passport::actingAs($user);

    $payload = [
        'shift_id' => $shift->id,
        'client_uuid' => $clientUuid,
        'cart' => [
            ['product_id' => 'prod-123', 'quantity' => 1, 'unit_price' => 20.00]
        ],
        'payments' => [
            ['payment_method' => 'cash', 'amount' => 20.00]
        ]
    ];

    // 1. Execute first sync request (Expected to succeed)
    $response1 = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/pos/sync-offline-orders", $payload);

    $response1->assertStatus(200);
    expect($response1->json('linkedExisting'))->toBeFalse();

    // 2. Execute duplicate sync request with matching client_uuid
    $response2 = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/pos/sync-offline-orders", $payload);

    // 3. Assert request completes successfully but flags existing link
    $response2->assertStatus(200);
    expect($response2->json('linkedExisting'))->toBeTrue();

    // Verify database contains only 1 order row
    expect(PosOrder::where('client_uuid', $clientUuid)->count())->toBe(1);
});
```

---

## 3. Frontend E2E & Component Verification

Tests are written in Playwright (E2E) and Vitest (Component) under the `frontend/` workspace.

### E2E Journeys
1. **Offline Checkout Flow**:
   - Enable network offline throttle in browser.
   - Scan barcode and click "Pay Cash".
   - Assert receipt includes `PENDING SYNC` watermark.
   - Assert order exists inside IndexedDB table `offline_orders`.
   - Restore network connection.
   - Intercept background heartbeats, verify sync execution, and assert watermark updates to `SYNCED`.

2. **Touch Grid Navigation**:
   - Select product categories from upper tabs.
   - Assert quick key product listings render instantly.
   - Add multiple items to the cart, verify total updates correctly, and test parking/resuming cart state.

---

## 4. Postman Integration Scenarios

All integration test routes are verified inside `docs/postman/erp_collection.json`.

### Automated Postman Tests:
- **Initialize Terminal Shift**:
  - Request: `POST /api/v1/pos/shifts/open` with float.
  - Script asserts shift status is open.
- **Process Barcode Checkout**:
  - Request: `POST /api/v1/pos/orders/checkout` with cart details.
  - Script asserts double-entry GL journal allocations.
- **Close & Reconcile**:
  - Request: `POST /api/v1/pos/shifts/{{shift_id}}/close` with counted drawer totals.
  - Script asserts variance logging checks.
