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

### `leave_types` Table
Defines the types of leaves available (e.g. vacation, sick, special, unpaid, maternity).
```sql
CREATE TABLE leave_types (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) NOT NULL,                  -- unique slug (e.g. vacation, sick, special, unpaid, maternity)
    default_allowance DECIMAL(5,2) DEFAULT 0.00, -- default days per year
    is_paid BOOLEAN DEFAULT TRUE,                -- paid vs unpaid status
    gender_restriction VARCHAR(10) NULL,         -- 'female', 'male', or NULL (any)
    is_accrued BOOLEAN DEFAULT FALSE,            -- accrued monthly vs available upfront
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX leave_types_code_uidx ON leave_types(tenant_id, code) WHERE deleted_at IS NULL;
CREATE INDEX leave_types_tenant_idx ON leave_types(tenant_id);
```

### `employee_leave_allocations` Table
Tracks each employee's leave entitlement and balances per year.
```sql
CREATE TABLE employee_leave_allocations (
    id UUID PRIMARY KEY,
    employee_id UUID NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id UUID NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    year INT NOT NULL,                           -- e.g. 2026
    allocated_days DECIMAL(5,2) NOT NULL,        -- custom or default allowance
    used_days DECIMAL(5,2) DEFAULT 0.00,         -- taken leaves (status = approved)
    pending_days DECIMAL(5,2) DEFAULT 0.00,      -- locked leaves in workflow (status = pending)
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX emp_leave_alloc_uidx ON employee_leave_allocations(employee_id, leave_type_id, year) WHERE deleted_at IS NULL;
CREATE INDEX emp_leave_alloc_tenant_idx ON employee_leave_allocations(tenant_id);
```

### `leaves` Table
Stores individual employee leave requests.
```sql
CREATE TABLE leaves (
    id UUID PRIMARY KEY,
    employee_id UUID NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id UUID NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(4,1) NOT NULL,                  -- calculated decimal duration
    leave_session VARCHAR(16) DEFAULT 'full_day',-- 'full_day', 'morning', 'afternoon'
    reason TEXT NULL,
    status VARCHAR(50) DEFAULT 'pending',         -- pending, approved, rejected, cancelled
    
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE INDEX leaves_lookup_idx ON leaves(tenant_id, employee_id, start_date);
```

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

1. **Leave Type Configuration & System Defaults**:
   - Leave types must be configured inside the `leave_types` catalog before they can be assigned or requested.
   - The system seeds and enforces the following default leave types upon tenant provisioning:
     - **Vacation/Annual Leave** (`code = 'vacation'`): `default_allowance = 18.00` days per year, paid (`is_paid = true`), accrued monthly (`is_accrued = true`), no gender restriction.
     - **Special Leave** (`code = 'special'`): `default_allowance = 7.00` days per year, paid, upfront allocation (`is_accrued = false`), no gender restriction.
     - **Sick Leave** (`code = 'sick'`): `default_allowance = 7.00` days per year, paid, upfront allocation (`is_accrued = false`), no gender restriction.
     - **Unpaid Leave** (`code = 'unpaid'`): `default_allowance = 5.00` days per year, unpaid (`is_paid = false`), upfront allocation (`is_accrued = false`), no gender restriction.
     - **Maternity Leave** (`code = 'maternity'`): `default_allowance = 90.00` days (3 months) per year, paid, upfront allocation (`is_accrued = false`), restricted to `female` employees (`gender_restriction = 'female'`).

2. **Employee Leave Allocation & Lifecycle**:
   - Employees do not inherit generic limits directly; they must have an active allocation record in `employee_leave_allocations` for the request year.
   - **Auto-Allocation on Hire/Onboard**: A workforce listener (`EmployeeCreated` / `EmployeeOnboarded`) triggers when an employee record is activated:
     - Iterates through all active `leave_types`.
     - Validates the employee's eligibility based on `gender_restriction`. For example, `maternity` leave is only allocated if `employee.gender === 'female'`.
     - Inserts an `employee_leave_allocations` row with `allocated_days = default_allowance` for the current year.
   - **Manual Adjustments**: HR administrators with `hrm.leave.write` can adjust `allocated_days` per employee (e.g. adding extra days for senior staff).

3. **Work Schedule-Based Duration Calculation**:
   - The leave request duration `days` is computed dynamically based on the employee's work schedule hours rather than calendar days:
     - The service iterates day-by-day between `start_date` and `end_date` (inclusive).
     - For each day, it resolves the effective schedule via `WorkScheduleService::resolveFor($date, $employee)`.
     - If `is_work_day` is `false`, the day adds `0.0` to the leave duration.
     - If `is_work_day` is `true`, it sums the working hours in `intervals` (e.g., `08:00 - 12:00` + `13:00 - 17:00` = 8 hours).
     - The day's contribution is calculated as:
       $$\text{Daily Contribution} = \frac{\text{Daily Scheduled Hours}}{\text{Standard Daily Hours (from settings: hrm.payroll.monthly_work_hours_standard / 20 or default 8.0)}}$$
       *(e.g., standard Monday-Friday 8 hours = 1.0 day; Saturday half-day 4 hours = 0.5 day).*
     - If the request specifies a half-day session (`morning` or `afternoon`) on that day, the contribution is halved:
       $$\text{Daily Contribution} = \frac{\text{Daily Scheduled Hours}}{\text{Standard Daily Hours}} \times 0.5$$
       *(e.g., morning leave on Saturday = 0.25 day; morning leave on Friday = 0.5 day).*
     - The sum of all daily contributions is saved as a decimal in `leaves.days`.

4. **Available Balance & Accrual Validation**:
   - The system checks available balances before allowing submissions. The balance validation formula is:
     $$\text{Available Balance} = \text{Entitled Days} - (\text{used\_days} + \text{pending\_days})$$
   - Where **Entitled Days** is computed based on accrual rules:
     - If `leave_types.is_accrued` is `true` (e.g., vacation leave), it is computed pro-rata:
       $$\text{Entitled Days} = \text{Allocated Days} \times \frac{\text{Months from Year Start or Hire Month}}{12}$$
     - If `leave_types.is_accrued` is `false`, **Entitled Days** is simply `allocated_days` (full amount available upfront).
   - If requested duration `days` exceeds the `Available Balance`, the submission fails with `422 Unprocessable Entity`, unless `hrm.leave.allow_negative_balance` is enabled.

5. **eApprovals Integration & Locking**:
   - Every leave request must go through the approval process.
   - Upon submission, the request status is set to `pending`, and an `ApprovalRequest` is dispatched to the `eApprovals` engine.
   - **Balance Locking**: The requested `days` are immediately added to `pending_days` on the employee's allocation row to prevent double-spending while under review.
   - **Resolution Listener**: When the approval request finishes:
     - **Approved**: Leave status is set to `approved`. The `pending_days` is decremented, and `used_days` is incremented by the request duration.
     - **Rejected**: Leave status is set to `rejected`. The `pending_days` is decremented, releasing the lock.
     - **Withdrawn / Cancelled**: If the request is withdrawn, `pending_days` is decremented and the leave is soft-deleted.

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
   - **Absent**: If no clock-in log exists by the time of daily reconciliation, no approved leave is recorded, and the day is a scheduled work day (`is_work_day = true`) under the employee's resolved `WorkSchedule`.

---

## 4. Workflows & Integration

### Daily Reconciliation Cron Job (`ReconcileAttendanceJob`)
1. Runs daily at **01:00 AM** to process the previous day's attendance.
2. Iterates over all active employees:
   - Resolves the active shift for the date.
   - Resolves the employee's `WorkSchedule` for the day of the week.
   - Checks for `attendance_logs`. If missing:
     - Checks if the day is marked as `is_work_day = false` in the schedule. If so, creates or keeps a log with status `weekend` / non-workday (does not count as absence).
     - Checks if the employee has an approved `Leave` row for that date. If so, records status based on leave type (e.g. `paid_leave`, `unpaid_leave`).
     - Otherwise, if it is a scheduled workday (`is_work_day = true`), creates an `attendance_logs` row with status `absent`.
   - Resolves late/early-out flags based on raw timestamps.

### Payroll Engine Integration
1. When generating payslips in `PayrollService::computeFor()`, the payroll engine must query the attendance reconciliation data for the target period.
2. **Deductions**:
   - Each `absent` day and `unpaid_leave` day results in a salary deduction:
     $$\text{Deduction} = \frac{\text{Base Salary}}{\text{Workdays in Period}} \times \text{Days Absent}$$
3. **Overtime Earnings**:
   - Aggregates approved `overtime_requests` rows:
     $$\text{OT Earnings} = \sum \left( \text{Approved OT Hours} \times \text{Hourly Rate} \times \text{Multiplier} \right)$$
     Where $\text{Hourly Rate} = \frac{\text{Base Salary}}{\text{monthly_work_hours_standard}}$ (derived from tenant payroll settings).

---

## 5. API Endpoint Specifications

All paths require `auth:api` and the `X-Tenant-Handle` header.

| Method | Path | Required Permissions | Description |
|---|---|---|---|
| **GET** | `/api/v1/leaves` | `hrm.leave.read` or `.read.self` | List leave requests (filters to self if no admin permissions) |
| **POST** | `/api/v1/leaves` | `hrm.leave.write` or `.write.self` | Submit leave request (validates balance and overlaps) |
| **POST** | `/api/v1/leaves/{id}/withdraw` | `hrm.leave.write.self` | Withdraw a pending request (cancels approval request) |
| **GET** | `/api/v1/employees/{id}/leave-balance` | `hrm.leave.read` or `.read.self` | Fetch balance sheet per leave type |
| **GET** | `/api/v1/hrm/leave-types` | `hrm.leave.read` | List all configured leave types |
| **POST** | `/api/v1/hrm/leave-types` | `hrm.leave.write` | Create or update a leave type configuration |
| **DELETE** | `/api/v1/hrm/leave-types/{id}` | `hrm.leave.write` | Delete a leave type configuration |
| **GET** | `/api/v1/hrm/leave-allocations` | `hrm.leave.read` | List employee leave allocations |
| **POST** | `/api/v1/hrm/leave-allocations` | `hrm.leave.write` | Adjust or assign leave allocations manually |
| **POST** | `/api/v1/attendance/clock-in` | `hrm.attendance.clock.self` | Record check-in (checks IP, geofence, grace periods) |
| **POST** | `/api/v1/attendance/clock-out` | `hrm.attendance.clock.self` | Record check-out (validates active clock-in session) |
| **GET** | `/api/v1/attendance/logs` | `hrm.attendance.read` or `.read.self` | Fetch logs with date range and employee filters |
| **POST** | `/api/v1/attendance/reconcile` | `hrm.attendance.write` | Manually trigger the reconciliation process |
| **GET** | `/api/v1/hrm/shifts` | `hrm.shift.read` | List all configured shifts |
| **POST** | `/api/v1/hrm/shifts` | `hrm.shift.write` | Create a shift schedule |
| **POST** | `/api/v1/hrm/overtime-requests` | `hrm.overtime.write.self` | Apply for overtime hours |
| **PATCH**| `/api/v1/hrm/overtime-requests/{id}/process` | `hrm.overtime.write` | Approve or reject a pending overtime request |
| **GET** | `/api/v1/hrm/work-schedules` | `settings.read` or `hrm.employee.write` | Fetch the active schedule for global, department, or employee targets |
| **POST** | `/api/v1/hrm/work-schedules` | `settings.write` or `hrm.employee.write` | Save schedule config / overrides for global, department, or employee targets |
| **DELETE**| `/api/v1/hrm/work-schedules` | `settings.write` or `hrm.employee.write` | Delete overrides for a department or employee, returning them to inheritance |

