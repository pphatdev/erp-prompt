# Task Checklist: Unified Calendar and Holiday Management

> See [`skills/calendar/skill.md`](../../skills/calendar/skill.md) for the canonical Calendar scope. This module is the execution engine for company holidays, employee leaves, shift schedule previews, and payroll holiday multipliers.

Legend: [x] shipped, [ ] planned

---

## A. Core Database and Model Scaffolding (Planned)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.A*

- [ ] Create tenant migration `2026_06_01_000002_create_calendar_tables.php` setting up holidays and calendar_events.
- [ ] Set up primary key UUID boots, `SoftDeletes`, and `Auditable` traits on `Holiday` and `CalendarEvent`.
- [ ] Ensure database constraints (polymorphic index on `calendar_events` and date index on `holidays`).
- [ ] Import and verify multi-tenant scoping via `BelongsToTenant`.

---

## B. Backend Services and Logic (Planned)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.B, § 3*

- [ ] **Company Holiday Registry**:
  - [ ] Implement `HolidayService::createHoliday()` recording names, dates, and overtime multipliers.
  - [ ] Implement weekend compensatory holiday generators creating adjacent Monday holidays when enabled.
  - [ ] Build math validators resolving overtime pay multipliers on holiday overtime logs (3.0x hourly rates).
- [ ] **Unified Event Compilation**:
  - [ ] Implement `CalendarEventService::getCombinedEvents()` querying holidays, leaves, shifts, and CRM schedules.
  - [ ] Enforce date query limits validation (max 90 days query range limits).
  - [ ] Implement privacy masking in `CalendarEventResource` hiding sick leaves details from unauthorized employees.
- [ ] **Attendance Reconciler Override**:
  - [ ] Update `ReconcileAttendanceJob` daily background reconciler to query registered holidays.
  - [ ] Ensure absent employees on holidays are marked with holiday status instead of absent status.
  - [ ] Reconcile payroll standard monthly workdays counts to subtract recognized holidays during period close.

---

## C. API Layer and Access Policies (Planned)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 1, § 2.B*

- [ ] Wire Calendar routing inside `routes/tenant.php` prefix `api/v1/calendar/`.
- [ ] Create controllers `HolidayController` and `CalendarEventController`.
- [ ] Implement Resources serialization formatting snake_case fields into camelCase JSON envelopes.
- [ ] Create and register permissions policies (`HolidayPolicy` and `CalendarEventPolicy`) in `TenantServiceProvider`.
- [ ] Seed standard calendar permissions in the central database tables.

---

## D. Frontend Page Scaffolding and Routing (Planned)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.C*

- [ ] Scaffold folder structure inside Nuxt: `frontend/pages/calendar/`.
- [ ] Register navigation routes and icons (`ti-calendar`) inside the sidebar configuration gating on `calendar.event.read` permission.
- [ ] Define the `useCalendar` composables (`frontend/composables/useCalendar.ts`) and register Pinia store (`frontend/stores/calendar.ts`).

---

## E. Frontend Workspaces and Views (Planned)
*Reference: [`skills/calendar/overview.md`](../../skills/calendar/overview.md) § 1-4*

### 1. Holiday Configurations Page (`/calendar/settings`)
- [ ] Create holiday list manager allowing admins to register public dates, toggle recurrence, and adjust overtime multipliers.

### 2. Interactive Unified Calendar Dashboard (`/calendar`)
- [ ] **Multi-Layout Calendar Grid**: Render calendar widget supporting monthly, weekly, daily, and agenda layouts using PrimeVue elements.
- [ ] **Toglable Layer Checks**: Build checkbox filters showing and hiding holidays, leaves, shifts, and CRM appointments in real-time.
- [ ] **Event Metadata Drawer**: Side drawer displaying event descriptions, employee profiles, and linked leaves data when clicked.
- [ ] **Shift Swap Modal**: Modals enabling employees to request shift swaps and submit them to managers.

---

## F. Integration and QA Testing (Planned)
*Reference: [`skills/calendar/testing.md`](../../skills/calendar/testing.md) § 1-3*

- [ ] **Backend Pest Test Suite**:
  - [ ] Write `TenancyIsolationTest` asserting cross-tenant holiday queries return `404`.
  - [ ] Write `LeavePrivacyMaskingTest` verifying sick leaves show as "Leave - Confirmed" without hrm.leave.read permission.
  - [ ] Write `HolidayCompensatoryTest` asserting weekend dates trigger Monday virtual compensatory holidays.
- [ ] **Postman Collections Sync**:
  - [ ] Add holiday creation and event query scenarios inside `docs/postman/erp_collection.json`.
