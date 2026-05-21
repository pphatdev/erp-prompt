# Testing Strategy: HRM Time Off & Attendance

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
|---|---|---|
| **P0** | **Tenancy Isolation** | Attendance logs, shift schedules, overtime requests, and leaves must be strictly isolated to the `tenant_id` context. |
| **P0** | **Security & Geofencing** | Clock-in attempts must fail with `422` if coordinates fall outside the office radius, or if the client IP does not match the whitelist. |
| **P1** | **Calculations** | Leave balances must accurately subtract YTD leaves, account for pro-rata monthly accruals, and support half-day increments. |
| **P1** | **State Transitions** | The reconciliation cron job must accurately categorize absences vs. approved leave days, and evaluate Late/Present status using shift grace boundaries. |
| **P2** | **Event Integration** | Leave status modifications in `eApprovals` must fire event listeners that sync changes back to the leaves table. |

---

## 2. Backend Testing (Pest PHP)

Use these test structures to validate behavior in Pest suites.

### Tenancy Isolation (P0)
Verify that Tenant A cannot read, update, or check into Tenant B's shifts.
```php
it('enforces tenant isolation on attendance log resources', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $employeeA = Employee::factory()->forTenant($tenantA)->create();
    $employeeB = Employee::factory()->forTenant($tenantB)->create();

    // Create log for Tenant A employee
    $logA = AttendanceLog::factory()->forTenant($tenantA)->create([
        'employee_id' => $employeeA->id,
        'date' => now()->toDateString(),
    ]);

    // Authenticate as employee B (Tenant B)
    $this->actingAs($employeeB->user)
        ->getJson("/api/v1/attendance/logs?employeeId={$employeeA->id}")
        ->assertStatus(403); // Or empty array depending on scope filters
});
```

### GPS Geofencing (P0)
Assert that Clock-In attempts check office proximity and reject spoofed/distant coordinates.
```php
it('rejects clock in attempts outside the department geofence', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->forTenant($tenant)->create();
    
    // Set office coordinates (e.g. Phnom Penh office)
    $employee->department->update([
        'latitude' => 11.5564, 
        'longitude' => 104.9282
    ]);

    // Attempt clock in from Paris coordinates (distant coordinates)
    $this->actingAs($employee->user)
        ->postJson('/api/v1/attendance/clock-in', [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});
```

### Leave Balance Restrictions (P1)
Assert that employees cannot request leave that exceeds their remaining available balance.
```php
it('fails to submit leave request if balance is insufficient', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->forTenant($tenant)->create();
    
    $leaveType = LeaveType::factory()->forTenant($tenant)->create([
        'annual_allowance' => 10,
    ]);

    // Employee already used 8 days
    Leave::factory()->forTenant($tenant)->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->startOfYear()->addDays(7)->toDateString(), // 8 days total
        'days' => 8,
        'status' => 'approved',
    ]);

    // Attempting to request 3 days (total YTD would exceed 10)
    $this->actingAs($employee->user)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(), // 3 days
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Insufficient leave balance (2 day(s) remaining).');
});
```

### Attendance Status Resolution (P1)
Validate that shift grace boundaries resolve correct attendance statuses.
```php
it('resolves attendance status to late when exceeding the grace period', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->forTenant($tenant)->create();
    
    $shift = Shift::factory()->forTenant($tenant)->create([
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'grace_period_minutes' => 15,
        'half_day_threshold_minutes' => 120,
    ]);
    
    EmployeeShift::create([
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subDay()->toDateString(),
        'tenant_id' => $tenant->id,
    ]);

    // Clocking in at 08:20 (exceeds 15 min grace, within 120 min half-day threshold)
    $this->actingAs($employee->user)
        ->postJson('/api/v1/attendance/clock-in', [
            'latitude' => $employee->department->latitude,
            'longitude' => $employee->department->longitude,
            'clock_time' => now()->toDateString() . ' 08:20:00',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'late');
});
```

---

## 3. Postman Collection Verification

Ensure endpoints in `docs/postman/erp_collection.json` under `HRM › Time Off & Attendance` assert the following:

1. **Headers**:
   - `X-Tenant-Handle` header is dynamically resolved and passed.
   - `Authorization: Bearer {{token}}` matches active user context.
2. **Clock-In Error Response**:
   - Verify that triggering `/api/v1/attendance/clock-in` without geolocation fields returns `422 Unprocessable Entity`.
3. **Leave Request Balances**:
   - Verify that `/api/v1/employees/{id}/leave-balance` returns a JSON object containing keys: `leaveTypeId`, `name`, `annualAllowance`, `used`, and `remaining`.
