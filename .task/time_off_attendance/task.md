# Task: HRM Time Off & Attendance

## Phase A — Documentation (completed)

- [x] `skills/hrm/time_off_attendance/rules.md` — permissions, schemas, validations, geofencing, reconciliation, payroll integration, endpoint table.
- [x] `skills/hrm/time_off_attendance/flow.md` — clock-in geofence, leave + eApprovals, daily reconciliation mermaid charts.
- [x] `skills/hrm/time_off_attendance/testing.md` — P0/P1/P2 priority matrix, Pest test scaffolds, Postman checklist.
- [x] Sync with master tracker (`.task/task.md`).

## Phase B — Backend implementation

Sliced delivery — each slice is a self-contained vertical (migration → model → service → controller → routes → tests):

| # | Slice | Status |
|---|---|---|
| 1 | **Shifts + EmployeeShifts CRUD** — foundation for assignment lookup | [x] done — migration, models, policy, service, controller, requests, resources, routes (7 endpoints) |
| 2 | Attendance logs + clock in/out (Haversine geofence, IP whitelist, status resolution) | [x] done — migration adds geofence cols to departments + attendance_logs table; service implements Haversine, IPv4 CIDR whitelist, status resolution against active shift; 4 endpoints |
| 3 | Overtime requests CRUD + `/process` (eApprovals-compatible) | [x] done — migration, model, policy with `process`/`cancel` abilities, service auto-promotes weekend dates to 2.0x, controller with 5 endpoints |
| 4 | Daily reconciliation job + payroll deductions/earnings | [x] done — `AttendanceService::reconcileAll()` (idempotent), `ReconcileAttendanceJob` scheduled `dailyAt('01:00')`, `POST /attendance/reconcile` manual trigger, `PayrollService::computeFor()` extended for absent + unpaid_leave deductions and weighted OT earnings |
| 5 | Half-day leave (`leave_session`) + pro-rata monthly accrual | [x] done — migration widens `days` to NUMERIC(4,1) + adds `leave_session`; service rejects half-day spans across multiple dates, computes accrued = annual/12 × elapsed months from hired_at (capped at annual), `lockedDaysFor` sums approved+pending so parallel requests can't double-spend |

Slices 1, 3, 5 are independent. Slice 2 depends on 1. Slice 4 depends on 2 + 3.

## Phase C — Frontend & Postman

| # | Deliverable | Status |
|---|---|---|
| C1 | Postman collection — Shifts (7) + Attendance (5) + Overtime Requests (5) sub-folders added under HRM; Leaves body shows `leave_session` and balance description mentions `accrued`. | [x] done |
| C2 | `/hrm/shifts` page — CRUD list + create/edit modal, kebab dropdown row actions. Sidebar entry. | [x] done |
| C3 | `/attendance` page — list with status/date/employee filters; self-service Clock In / Clock Out buttons using browser geolocation; admin manual reconcile. | [x] done |
| C4 | `/hrm/overtime` page — list + submit modal + kebab approve/reject/cancel. | [x] done |
| C5 | Leaves page — `leave_session` selector (full_day · morning · afternoon) locks end_date for half-days; days column shows fractional with AM/PM suffix. | [x] done |
| C6 | `/employees/:id` — new Attendance tab with status tiles + recent log table, lazy-loaded. | [x] done |

### Workflow status keys to seed

- `hrm.attendance` — `present`, `late`, `early_out`, `half_day`, `absent`, `paid_leave`, `unpaid_leave`, `weekend`, `holiday`.
- `hrm.overtime` — `pending` (initial), `approved`, `rejected`, `cancelled`.

### Permission keys to seed (and add to `employee` role for `.self`)

`hrm.attendance.{read,write,delete,export}` + `.read.self` + `.clock.self`
`hrm.shift.{read,write,delete}`
`hrm.overtime.{read,write,delete,export}` + `.read.self` + `.write.self`

- Tenant geofence / IP whitelist live in tenant settings — slice 2 introduces a JSON column or new model if no settings store exists.
- Holiday calendar deferred — reconciliation treats Sat/Sun as weekend by default.
- Tests prioritise P0 (tenant isolation, geofence) > P1 (calculations, status resolution) > P2 (event sync).

## Phase D — Leave Types & Employee Allocations (Backend)

| # | Slice | Status |
|---|---|---|
| 6 | **Leave Types & Allocations Migrations & Models**: Add code, default_allowance, is_paid, gender_restriction, is_accrued to `leave_types`; create `employee_leave_allocations` table & model (UUID, unique index employee_id/leave_type_id/year). | [ ] todo |
| 7 | **Defaults Seeding & Auto-Allocation Listener**: Seed system defaults (18 Vacation - monthly accrual, 7 Special, 7 Sick, 5 Unpaid, 90-day Maternity). Create event listener on Employee creation/hire to auto-provision entitlements for the current year, enforcing gender eligibility rules. | [ ] todo |
| 8 | **Schedule-Based Decimal Duration Calculation**: Refactor `LeaveService::submitRequest` to calculate duration by resolving work schedule intervals for each day in range, dividing by standard daily hours (default 8), and multiplying half-day sessions by 0.5. | [ ] todo |
| 9 | **Balance Validation & eApprovals Locking**: Refactor balance validation to query `employee_leave_allocations`. Update `SyncLeaveFromApproval` and `submitRequest` to increment `pending_days` on submission, decrement `pending_days` and increment `used_days` on approval, or release lock on rejection/withdrawal. | [ ] todo |
| 10| **Leave Types & Allocations REST API**: Implement CRUD controllers and FormRequests for Leave Types and manual allocations overrides. | [ ] todo |

## Phase E — Frontend & Postman (Leave Settings & Allocations)

| # | Deliverable | Status |
|---|---|---|
| E1 | Postman folder - Leave Types & Allocations requests (CRUD + manual adjustments) | [ ] todo |
| E2 | Leave Types Settings tab in `/settings/apps/hrm` — configuration grid + add/edit leave type modal. | [ ] todo |
| E3 | Leave Allocations tab in `/settings/apps/hrm` — list employee entitlement balances + manual adjustment side-drawer. | [ ] todo |
| E4 | Submit Request update — display remaining balance per leave type in dropdown selections, and show live schedule-based duration preview before submission. | [ ] todo |

### Additional Permissions to Seed
`hrm.leave.allocate` — Admin permission to view/modify employee allocations.

