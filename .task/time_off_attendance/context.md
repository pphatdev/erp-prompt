# Feature Context: HRM Time Off & Attendance

Design, rule creation, and technical planning for the HRM Time Off & Attendance module in the multi-tenant ERP system.

## Objective
Establish the coding rules, database schemas, workflow flows, permission gates, and testing guidelines for:
1. **Leave & Time Off Management**: Accrual configurations, pro-rata calculations, half-day leaves, available balance validation, and `eApprovals` integration.
2. **Attendance Tracking**: Clock-in/out mechanisms (GPS coordinates, IP whitelisting, web logs), status evaluation (Late, Absent, Early Out), shift assignments, and daily reconciliation cron jobs.
3. **Overtime requests & multipliers**: 1.5x, 2.0x, and 3.0x hourly rates.
4. **Payroll and FMS integration**: Feeding attendance deductions and overtime hours into the payroll service.

## Architectural Guidelines
- **Tenant Isolation (P0)**: All attendance logs, leaves, shifts, and overtime requests are tenant-scoped via the `BelongsToTenant` trait and must use dynamic tenant connections.
- **Database Safety**: Ensure composite indexes on clock-in sheets ignore soft-deleted entries to avoid 23505 constraints on rehires/re-clocks.
- **Audit Logging**: Use `Auditable` trait on all core models (`Leave`, `AttendanceLog`, `Shift`, `OvertimeRequest`) to track modifications.
- **Thin Controllers**: Validation in FormRequests, business logic in service layers (`LeaveService`, `AttendanceService`, `OvertimeService`), and responses formatted via API Resources.
- **Self-Service Gating**: Standard employees can access only their own records via `.self` scope, while admins/HR managers utilize module-level read/write permissions.
