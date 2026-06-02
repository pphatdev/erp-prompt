---
name: unified-calendar
description: Unified calendar displaying company holidays, employee leaves, shifts, and CRM schedules, integrated with payroll costing and attendance reconciliations.
---
# Unified Calendar and Holiday Management

Use this skill when developing, modifying, or extending features related to company holidays, employee leave calendars (days off), shift schedule previews, unified calendar overlays, or payroll holiday multipliers. This module maps scheduling data and drives compliance overtime calculations, so multi-tenant isolation and mathematical consistency are primary concerns.

## Module Surface

```
Calendar (sidebar group)
├── Company Calendar               - Interactive grid showing holidays, leaves, and shifts
├── Holiday Settings               - CRUD for company holidays, regional tags, and overtime rates
├── My Schedule (Self-Service)     - Personal calendar with shifts, holidays, and team leaves
└── Schedule Overrides             - Manager view to adjust shift swaps and holiday coverages
```

| Layer | Path |
|---|---|
| **Controllers** | `app/Tenants/Modules/Calendar/Controllers/{HolidayController, CalendarEventController}.php` |
| **Services** | `app/Tenants/Modules/Calendar/Services/{HolidayService, CalendarEventService}.php` |
| **Resources** | `app/Tenants/Modules/Calendar/Resources/{HolidayResource, CalendarEventResource}.php` |
| **Models** | `app/Models/Tenant/{Holiday, CalendarEvent}.php` |
| **Policies** | `app/Policies/{Holiday, CalendarEvent}Policy.php` |
| **Migrations** | `database/migrations/tenant/{date}_create_holidays_tables.php` |
| **Seeder** | `TenantDatabaseSeeder.php` - seeds default national holidays, calendar event categories, and standard overtime parameters. |
| **Pages** | `frontend/pages/calendar/{index, settings}.vue` |

---

## Permission Slug Catalog

```
calendar.holiday.read
calendar.holiday.write
calendar.holiday.delete
calendar.event.read
calendar.event.write.self                  - Self-service: Read own shift and holiday calendars
calendar.event.read.self                   - Self-service: Log shift swap requests
```

---

## Critical Rules

### 1. Multi-Tenant Scoping (P0)
- **Database Separation**: Every query to the `holidays` and `calendar_events` tables must use the tenant connection initialized by the InitializeTenancyByHandle middleware. Scoping is enforced using the BelongsToTenant trait.
- **Cross-Tenant Guarding**: API requests targeting `/api/v1/calendar/holidays/{id}` must trigger model resolution scoped to the tenant. Requests attempting to read resources across tenants must return a 404 Not Found error to prevent security leaks.

### 2. Holiday Calendar Overrides and Overtime Math (P0)
- **Holiday Pay Escalation**: If an employee logs overtime hours on a registered holiday, `OvertimeService::calculateHours` must escalate the multiplier to the configured rate (default: 3.0x hourly pay) instead of standard weekend rates (2.0x).
- **Date Overlap Logic**: If a public holiday lands on a weekend (Saturday/Sunday), the system must check the tenant setting `calendar.holiday.compensatory_day`. If enabled, it automatically schedules a compensatory paid day off on the adjacent Monday.
- **Accrual Calculations**: The `PayrollService` must query active holidays to subtract recognized holidays from standard monthly workday counts during period-close payroll calculations.

### 3. Leave and Day Off Privacy Scoping (P1)
- **Privacy Masking**: Approved leave records (days off) are visible to all employees on the unified calendar, but the display title must be masked based on the viewer's permissions:
  * Users with `hrm.leave.read` see the full leave type and reason (e.g. "Sick Leave - Severe Migraine").
  * Users with `calendar.event.read` but without leave-read permissions see only "Leave - Confirmed" or "Day Off" with hidden details.
- **Self-Service Restrictions**: Regular employees can only query leave dates for members of their own designated department when using the search filters.

### 4. Attendance Reconciler Sync (P1)
- **Daily Attendance Reconciliation**: The daily background attendance job (`ReconcileAttendanceJob`) must query the holiday registry for the active date.
- **Status Override**: If an employee has no clock-in/out records on a holiday date, the system must set `attendance_logs.status = 'holiday'` instead of marking the employee as `absent`.

### 5. Multi-Layer Query Performance (P2)
- **Date Boundary Limits**: The frontend calendar widget queries events using date range boundaries (e.g., `GET /api/v1/calendar/events?start_date=2026-06-01&end_date=2026-06-30`). The controller must enforce date range boundaries (maximum 90 days range per query) to prevent memory crashes.
- **Eager Loading**: The calendar event resource must eagerly load associated polymorphic relationships (e.g. employee details, crm details) using conditional formatting to avoid N+1 querying issues.

---

## Status Flows

Holiday configurations and schedule swap events utilize the standard `workflow_statuses` system.

### Schedule Swap Workflow States
| State Key | Initial/Terminal | Meaning | Action Trigger |
|---|:---:|---|---|
| `pending` | Initial | Swap request submitted, awaiting manager review. | Employee submits request. |
| `approved` | Terminal | Roster updated, calendar reflects swap. | Manager signs off. |
| `rejected` | Terminal | Request denied, original roster remains. | Manager denies request. |

---

## Frontend Integration Standards

- **Touch and Viewport Styling**: The calendar dashboard (`frontend/pages/calendar/index.vue`) uses PrimeVue calendar panels styled to match the dark glassmorphism theme (`.glass-card`).
- **Interactive Layers**: Users can toggle calendar layers (Holidays, Leaves, Shifts, CRM Appointments) using a custom sidebar checkbox selector. Toggling layers updates the active reactive data arrays without a full page reload.

---

## Troubleshooting Matrix

| Symptom | Root Cause | Programmatic Resolution |
|---|---|---|
| **Holiday overtime rated as normal hours** | OvertimeService cannot find the holiday record due to timezone mismatches. | Ensure `OvertimeService` date matches are resolved using the tenant base timezone setting before querying the database. |
| **All employees see detailed sick leave notes** | Resource did not apply privacy masking logic. | Update `CalendarEventResource` to check the request user's permissions and mask the title to "Leave - Confirmed" if `hrm.leave.read` is missing. |
| **Calendar crashes when fetching active viewport** | The query is requesting years of records without limits. | Enforce minimum and maximum date boundaries inside the request validation request file (e.g., `start_date` and `end_date` are required and must span less than 90 days). |
| **Reconciler marks holiday as absent** | The daily reconciliation job executed before the holiday dates were loaded. | Configure the daily background job to resolve and load holidays using cache wrappers. |

---

## Read Next
- [`overview.md`](./overview.md) - Feature taxonomy, integrations, and concepts.
- [`rules.md`](./rules.md) - Permission matrices, DB schemas, and technical flows.
- [`flow.md`](./flow.md) - Visualizing holiday creation, leaves synchronization, and payroll runs.
- [`testing.md`](./testing.md) - Isolation, calculation rates, and QA test specs.
