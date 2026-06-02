# Testing Strategy: Unified Calendar and Holiday Management

This document outlines the testing priority matrix, backend Pest test implementations, frontend E2E and visual assertions, and API integration scenarios for the Unified Calendar module.

---

## 1. Priority Matrix (P0 - P2)

Testing must cover security, calculation accuracy, state machines, and real-time operations, prioritizing tenancy isolation and privacy masking above all else.

| Priority | Category | Requirement / Test Case | Focus Area |
|---|---|---|---|
| **P0** | **Tenancy Isolation** | Calendar data (holidays, events, swap requests) is strictly isolated by `tenant_id`. Access across tenants must throw `404 Not Found`. | DB Connection Isolation |
| **P0** | **Holiday Overtime Math** | Employees working on registered holidays receive 3.0x hourly pay rates. Mismatches trigger payroll close transaction rollbacks. | Cost Calculations |
| **P1** | **Leave Privacy Masking** | Users without `hrm.leave.read` receive a masked title "Leave - Confirmed" in response collections, securing sensitive medical/sick leave details. | Data Privacy & Scoping |
| **P1** | **Compensatory Days** | Weekend holidays automatically spawn virtual compensatory holidays on adjacent workdays, updating active calendars dynamically. | Scheduling Invariants |
| **P1** | **Attendance Override** | Absent employees on registered holiday dates have their attendance log status updated to "holiday" instead of "absent" by daily reconcilers. | Reconciler Automations |
| **P2** | **Unified Event Query** | Querying consolidated events validates date boundaries, returning a 422 if parameters exceed the 90 days query range limit. | Query Performance |

---

## 2. Backend Testing (Pest PHP)

Tests run exclusively on the `erp_system_test` database connection (enforced by `phpunit.xml`). Seeders must run to establish workflow states.

### A. Data Isolation Test (P0)
This test asserts that a request under Tenant A's connection context attempting to read Tenant B's active holiday is met with a clean `404 Not Found`.

```php
<?php

use App\Models\Tenant\Holiday;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Laravel\Passport\Passport;

uses(Tests\TestCase::class)->in(__DIR__);

test('employee cannot access holiday configurations from a different tenant', function () {
    // 1. Establish Tenant A & Tenant B
    $tenantA = createTenant('tenant-a');
    $tenantB = createTenant('tenant-b');

    // 2. Create Holiday in Tenant B
    tenancy()->initialize($tenantB);
    $holidayB = Holiday::create([
        'name' => 'Secret Tenant B Anniversary',
        'date' => '2026-06-05',
        'overtime_multiplier' => 3.00,
        'tenant_id' => 'tenant-b',
    ]);
    tenancy()->end();

    // 3. Authenticate User under Tenant A
    tenancy()->initialize($tenantA);
    $userA = User::factory()->create();
    $employeeA = Employee::factory()->create(['user_id' => $userA->id]);
    
    Passport::actingAs($userA);

    // 4. Request Tenant B's Holiday via Tenant A's API connection
    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'tenant-a',
    ])->getJson("/api/v1/calendar/holidays/{$holidayB->id}");

    // 5. Assert isolation hides the holiday and returns a 404
    $response->assertStatus(404);
});
```

### B. Leave Privacy Masking Test (P1)
This test verifies that a user without `hrm.leave.read` permission receives a masked leave title in the calendar events response.

```php
test('mask leave event details when request user lacks hrm.leave.read permission', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);

    $employee = Employee::factory()->create();
    
    // Create detailed leave request
    $leave = Leave::create([
        'employee_id' => $employee->id,
        'start_date' => '2026-06-12',
        'end_date' => '2026-06-12',
        'reason' => 'Severe Medical Surgery',
        'status' => 'approved',
        'tenant_id' => 'demo-tenant',
    ]);

    $event = CalendarEvent::create([
        'title' => 'Leave: ' . $employee->full_name . ' (Surgery)',
        'start_time' => '2026-06-12 08:00:00',
        'end_time' => '2026-06-12 17:00:00',
        'category' => 'leave',
        'eventable_type' => Leave::class,
        'eventable_id' => $leave->id,
        'tenant_id' => 'demo-tenant',
    ]);

    // Authenticate standard user without hrm.leave.read
    $user = User::factory()->create();
    Passport::actingAs($user);

    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->getJson("/api/v1/calendar/events?start_date=2026-06-01&end_date=2026-06-30");

    $response->assertStatus(200);
    
    // Assert title is masked in serialization
    $events = $response->json('data');
    expect($events[0]['title'])->toBe('Leave - Confirmed');
});
```

### C. Holiday Compensatory Day Resolution Test (P1)
This test asserts that registering a holiday on a Sunday automatically provisions a virtual compensatory holiday on the following Monday.

```php
test('weekend holiday automatically generates a Monday compensatory day', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);

    $user = User::factory()->create();
    $user->givePermissionTo('calendar.holiday.write');
    Passport::actingAs($user);

    // Register holiday on a Sunday (June 7, 2026 is Sunday)
    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/calendar/holidays", [
        'name' => 'National Day',
        'date' => '2026-06-07',
        'is_recurring' => false,
        'overtime_multiplier' => 3.00,
    ]);

    $response->assertStatus(201);

    // Assert database has created both Sunday holiday and Monday compensatory holiday
    $holidays = Holiday::whereBetween('date', ['2026-06-07', '2026-06-08'])->get();
    expect($holidays->count())->toBe(2);
    expect($holidays->where('date', '2026-06-08')->first()->name)->toContain('Compensatory');
});
```

---

## 3. Postman Integration Scenarios

All integration test routes are verified inside `docs/postman/erp_collection.json`.

### Automated Postman Tests:
- **Register Holiday**:
  - Request: `POST /api/v1/calendar/holidays` with date.
  - Script asserts response is 210 and schedules compensatory dates.
- **Unified Event Query**:
  - Request: `GET /api/v1/calendar/events` with date filters.
  - Script asserts response structures and masks private fields.
