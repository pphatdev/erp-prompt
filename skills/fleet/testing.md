# Testing Strategy: Fleet Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Vehicles, fuel logs, and maintenance logs must be strictly isolated per tenant; requests across tenants must fail. |
| **P0** | **Integrity** | Mileage updates must be monotonic (only increase) and maintenance schedules must trigger reliably. |
| **P1** | **API Contract** | Responses must use camelCase JSON keys, matching the standard pagination envelope; webhooks must verify headers. |
| **P1** | **Calculations** | Total Cost of Ownership (TCO) and fuel efficiency formulas must be unit-tested. |
| **P1** | **Audit Trail** | All mutations on Fleet models must write logs via the `Auditable` trait. |
| **P2** | **Alerts** | Maintenance reminders must reach the correct fleet managers. |

---

## 2. Backend Testing (Pest PHP)
All backend tests **MUST** execute against the dedicated test database `erp_system_test` (enforced by `phpunit.xml`). Never run tests on the development/production database (`erp_system`).

### A. Tenant Scoping & Isolation (P0)
- **Rule**: Every Fleet model must use `BelongsToTenant`. Tenant A must never be able to access Tenant B's fleet resources.
- **Pest Test Case**:
  ```php
  it('blocks tenant A user from viewing tenant B vehicles', function () {
      $tenantA = CentralTenant::factory()->create(['handle' => 'tenant-a']);
      $tenantB = CentralTenant::factory()->create(['handle' => 'tenant-b']);
      
      $vehicleB = Vehicle::factory()->create(['tenant_id' => $tenantB->handle]);
      
      $userA = User::factory()->create(['tenant_id' => $tenantA->handle]);
      
      actingAs($userA)
          ->withHeader('X-Tenant-Handle', 'tenant-a')
          ->getJson("/api/v1/vehicles/{$vehicleB->id}")
          ->assertStatus(403); // or 404 depending on routing resolver
  });
  ```

### B. CamelCase API Contract Verification (P1)
- **Rule**: API responses from `VehicleResource`, `FuelLogResource`, and `MaintenanceLogResource` must output keys in camelCase.
- **Pest Test Case**:
  ```php
  it('returns vehicle data in camelCase format', function () {
      $vehicle = Vehicle::factory()->create();
      
      actingAs($this->adminUser)
          ->withHeader('X-Tenant-Handle', $this->tenant->handle)
          ->getJson("/api/v1/vehicles/{$vehicle->id}")
          ->assertJsonStructure([
              'data' => [
                  'id',
                  'registrationNumber',
                  'currentMileage',
                  'maintenanceLogs',
                  'fuelLogs',
              ]
          ]);
  });
  ```

### C. Invariants & Business Logic (P0)
- **Rule**: Vehicle current mileage can only increase monotonically. Updates that lower mileage are ignored or rejected.
- **Test Case**: Call `VehicleService::logMaintenance` with a mileage lower than the vehicle's `current_mileage` and assert that the vehicle's `current_mileage` remains unchanged.

### D. Audit Logging Assertions (P1)
- **Rule**: Modifying fleet records must trigger `Auditable` trait captures.
- **Pest Test Case**: Assert that saving/updating a `Vehicle` creates an entry in the `audit_logs` table containing the user ID, event type (`created` or `updated`), and exact old/new key-value values.

### E. Telematics Webhook Authentication (P1)
- **Rule**: Telematics webhooks must require signature validation.
- **Test Case**: Post telemetry payloads with invalid signatures and assert `401 Unauthorized`.

---

## 3. Postman Verification
- **Collection**: `docs/postman/erp_collection.json` under the **Fleet** folder.
- **Headers**: Enforce `X-Tenant-Handle` and `Authorization: Bearer` on every mock request.
- **Mock Payload Guidelines**: Standardize on real-world test VIN values and registration formats.

---

## 4. Scheduling & Cron Checks (P1)
- **Rule**: `MaintenanceSchedulerJob` triggers daily check cycles.
- **Test Case**: Seed vehicles near mileage/date limits, execute `MaintenanceSchedulerJob`, and assert that pending `MaintenanceLog` entries or alert notifications are generated.

