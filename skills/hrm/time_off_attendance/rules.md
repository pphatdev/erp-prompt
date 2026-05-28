# HRM Time Off & Attendance: Implementation Rules

This document specifies the database schemas, validation rules, security permissions, and API standards for the **Time Off (Leave)** and **Attendance Tracking** submodules within the multi-tenant ERP system.

---

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action[.scope]` naming convention. 

### Permission Keys
- **Module**: `hrm`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin Scope
These permissions grant administrative access to records across the entire tenant.

| Feature | Read | Write | Delete | Export |
|---|---|---|---|---|
| `leave` | `hrm.leave.read` | `hrm.leave.write` | `hrm.leave.delete` | `hrm.leave.export` |
| `attendance` | `hrm.attendance.read` | `hrm.attendance.write` | `hrm.attendance.delete` | `hrm.attendance.export` |
| `shift` | `hrm.shift.read` | `hrm.shift.write` | `hrm.shift.delete` | - |
| `overtime` | `hrm.overtime.read` | `hrm.overtime.write` | `hrm.overtime.delete` | `hrm.overtime.export` |

### Feature Matrix — Self-Service Scope (`.self`)
These permissions are granted to the standard `employee` role and are paired with policy checks to ensure employees can only view or modify their own records.

| Permission | Endpoint(s) | Notes |
|---|---|---|
| `hrm.leave.read.self` | `GET /leaves`, `GET /leaves/{own}`, `GET /employees/{self}/leave-balance` | List endpoint force-filters to caller's `employee_id`. |
| `hrm.leave.write.self` | `POST /leaves`, `DELETE /leaves/{own-pending}` | Service layer asserts `employee_id` matches caller. Can only delete if status is `pending`. |
| `hrm.attendance.read.self` | `GET /attendance/logs` | Force-filters to caller's own records. |
| `hrm.attendance.clock.self` | `POST /attendance/clock-in`, `POST /attendance/clock-out` | Allows standard staff to log clock hours. |
| `hrm.overtime.read.self` | `GET /hrm/overtime-requests`, `GET /hrm/overtime-requests/{own}` | List filters to caller's `employee_id`. |
| `hrm.overtime.write.self` | `POST /hrm/overtime-requests`, `DELETE /hrm/overtime-requests/{own-pending}` | Request hours for authorization. |

---

## 2. Database Schema Specifications

All tables reside in the tenant database connection, utilize UUID primary keys, and must include `tenant_id` for isolation.

### `shifts` Table
Defines standard shift configurations.
```sql
CREATE TABLE shifts (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,           -- e.g., '08:00:00'
    end_time TIME NOT NULL,             -- e.g., '17:00:00'
    grace_period_minutes INT DEFAULT 0, -- Allowed delay before marked late
    half_day_threshold_minutes INT,     -- Delay after which it is counted as half-day absence
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE INDEX shifts_tenant_id_idx ON shifts(tenant_id);
```

### `employee_shifts` Table
Pivot mapping employees to specific shifts over a date range.
```sql
CREATE TABLE employee_shifts (
    id UUID PRIMARY KEY,
    employee_id UUID NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    shift_id UUID NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NULL, -- NULL represents active/current shift schedule
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX emp_shifts_lookup_idx ON employee_shifts(tenant_id, employee_id, start_date);
```

### `attendance_logs` Table
Stores raw check-in/out entries and resolved status.
```sql
CREATE TABLE attendance_logs (
    id UUID PRIMARY KEY,
    employee_id UUID NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    date DATE NOT NULL,                         -- Resolved calendar date
    check_in TIMESTAMP NULL,                    -- Exact check-in time
    check_out TIMESTAMP NULL,                   -- Exact check-out time
    status VARCHAR(50) DEFAULT 'present',        -- present, late, early_out, half_day, absent
    
    -- Geofence & Network Audit Fields
    check_in_ip VARCHAR(45) NULL,
    check_out_ip VARCHAR(45) NULL,
    check_in_lat DECIMAL(10, 8) NULL,
    check_in_lon DECIMAL(11, 8) NULL,
    check_out_lat DECIMAL(10, 8) NULL,
    check_out_lon DECIMAL(11, 8) NULL,
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
-- Unique constraint: an active employee can only have one log row per date
CREATE UNIQUE INDEX att_logs_emp_date_uidx ON attendance_logs(employee_id, date) WHERE deleted_at IS NULL;
CREATE INDEX att_logs_tenant_idx ON attendance_logs(tenant_id);
```

### `overtime_requests` Table
Tracks requested overtime hours and approval status.
```sql
CREATE TABLE overtime_requests (
    id UUID PRIMARY KEY,
    employee_id UUID NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    hours DECIMAL(5, 2) NOT NULL,            -- Number of hours requested
    rate_multiplier DECIMAL(3, 2) DEFAULT 1.50, -- 1.5x (normal), 2.0x (weekend), 3.0x (holiday)
    reason TEXT NULL,
    status VARCHAR(50) DEFAULT 'pending',     -- pending, approved, rejected, cancelled
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE INDEX ot_requests_lookup_idx ON overtime_requests(tenant_id, employee_id, date);
```

---

## 3. Core Business Logic & Calculations

### A. Time Off (Leave) Mechanics
1. **Half-Day Leave Requests**: 
   - The `leaves` table schema supports decimal `days` (e.g. `0.5` days).
   - Requests must specify a `leave_session` parameter: `full_day`, `morning`, or `afternoon`.
   - If `leave_session` is `morning` or `afternoon`, the duration `days` is strictly validated to be exactly `0.5`.
2. **Accrual & Balance Invariants**:
   - Monthly Accruals: Employees accrue leave days monthly on the first day of each month based on the formula: `Annual Allowance / 12` (rounded to 2 decimal places).
   - Pro-Rata: Employees joining mid-year accrue days starting only from their `hired_at` month.
   - Available Balance Validation: When submitting a leave request, the system checks:
     $$\text{Remaining Balance} = \text{Accrued Leaves} - \text{Approved Leaves YTD}$$
     If the requested `days` exceeds the `Remaining Balance`, the request must be rejected with a `422 Unprocessable Entity` response, unless the tenant's configuration permits negative balances.
3. **Approval Flow Integration**:
   - If the tenant has an active `eApprovals` workflow mapped to `module = hrm, type = leave`, the submit action must not directly write `status = approved`. It must dispatch an `ApprovalRequest` and set the leave status to `pending`.
   - While a request is in the approval queue, the requested days are locked. The balance calculation subtracts both `approved` and `pending` leaves to prevent double-spending allowances.
   - Leaving the legacy manual approval path is only allowed as a fallback if no workflow exists.

### B. Attendance & Shift Mechanics
1. **GPS Geofence Verification**:
   - The client coordinates (`check_in_lat`, `check_in_lon`) must be validated server-side.
   - The allowed coordinates are fetched from the department office location or tenant settings.
   - Distance calculation uses the **Haversine formula**. The request is rejected with `422` if the distance exceeds the configured threshold (default: `100` meters):
     $$d = 2R \arcsin\left(\sqrt{\sin^2\left(\frac{\Delta \phi}{2}\right) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(\frac{\Delta \lambda}{2}\right)}\right)$$
2. **IP Whitelisting**:
   - If IP whitelisting is active for the tenant, requests from unauthorized IP addresses must be blocked before calculating geofencing.
3. **Clock-In Status Verification**:
   - **Late**: If clock-in time exceeds the assigned shift start-time plus `grace_period_minutes`.
   - **Half-Day Absent**: If clock-in time exceeds `half_day_threshold_minutes` past shift start-time.
   - **Early Out**: If clock-out time is earlier than the assigned shift end-time.
   - **Absent**: If no clock-in log exists by the time of daily reconciliation, and no approved leave is recorded.

---

## 4. Workflows & Integration

### Daily Reconciliation Cron Job (`ReconcileAttendanceJob`)
1. Runs daily at **01:00 AM** to process the previous day's attendance.
2. Iterates over all active employees:
   - Identifies the assigned shift for the date.
   - Checks for `attendance_logs`. If missing:
     - Checks if the employee has an approved `Leave` row for that date.
     - If yes, records status based on leave type (e.g. `paid_leave`, `unpaid_leave`).
     - If no, and it is a scheduled workday (not weekend/holiday), creates an `attendance_logs` row with status `absent`.
   - Resolves late/early-out flags based on raw timestamps.

### Payroll Engine Integration
1. When generating payslips in `PayrollService::computeFor()`, the payroll engine must query the attendance reconciliation data for the target period.
2. **Deductions**:
   - Each `absent` day and `unpaid_leave` day results in a salary deduction:
     $$\text{Deduction} = \frac{\text{Base Salary}}{\text{Workdays in Period}} \times \text{Days Absent}$$
3. **Overtime Earnings**:
   - Aggregates approved `overtime_requests` rows:
     $$\text{OT Earnings} = \sum \left( \text{Approved OT Hours} \times \text{Hourly Rate} \times \text{Multiplier} \right)$$
     Where $\text{Hourly Rate} = \frac{\text{Base Salary}}{160}$ (or per-tenant standard).

---

## 5. API Endpoint Specifications

All paths require `auth:api` and the `X-Tenant-Handle` header.

| Method | Path | Required Permissions | Description |
|---|---|---|---|
| **GET** | `/api/v1/leaves` | `hrm.leave.read` or `.read.self` | List leave requests (filters to self if no admin permissions) |
| **POST** | `/api/v1/leaves` | `hrm.leave.write` or `.write.self` | Submit leave request (validates balance and overlaps) |
| **POST** | `/api/v1/leaves/{id}/withdraw` | `hrm.leave.write.self` | Withdraw a pending request (cancels approval request) |
| **GET** | `/api/v1/employees/{id}/leave-balance` | `hrm.leave.read` or `.read.self` | Fetch balance sheet per leave type |
| **POST** | `/api/v1/attendance/clock-in` | `hrm.attendance.clock.self` | Record check-in (checks IP, geofence, grace periods) |
| **POST** | `/api/v1/attendance/clock-out` | `hrm.attendance.clock.self` | Record check-out (validates active clock-in session) |
| **GET** | `/api/v1/attendance/logs` | `hrm.attendance.read` or `.read.self` | Fetch logs with date range and employee filters |
| **POST** | `/api/v1/attendance/reconcile` | `hrm.attendance.write` | Manually trigger the reconciliation process |
| **GET** | `/api/v1/hrm/shifts` | `hrm.shift.read` | List all configured shifts |
| **POST** | `/api/v1/hrm/shifts` | `hrm.shift.write` | Create a shift schedule |
| **POST** | `/api/v1/hrm/overtime-requests` | `hrm.overtime.write.self` | Apply for overtime hours |
| **PATCH**| `/api/v1/hrm/overtime-requests/{id}/process` | `hrm.overtime.write` | Approve or reject a pending overtime request |
