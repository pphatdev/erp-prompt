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

### Cross-cutting decisions

- Tenant geofence / IP whitelist live in tenant settings — slice 2 introduces a JSON column or new model if no settings store exists.
- Holiday calendar deferred — reconciliation treats Sat/Sun as weekend by default.
- Tests prioritise P0 (tenant isolation, geofence) > P1 (calculations, status resolution) > P2 (event sync).
