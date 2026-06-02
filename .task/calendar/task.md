# Task Checklist: Unified Calendar and Holiday Management

> See [`skills/calendar/skill.md`](../../skills/calendar/skill.md) for the canonical Calendar scope. This module is the execution engine for company holidays, employee leaves, shift schedule previews, and payroll holiday multipliers.

Legend: [x] shipped, [ ] planned

---

## A. Core Database and Model Scaffolding (Phase 1 - Shipped)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.A · v1 scope: Holidays + Unified Events query only; calendar_events stores custom events only (leaves/shifts/CRM unioned at query time).*

- [x] `2024_01_01_000095_create_calendar_tables.php` ALTERs the existing `holidays` table (already shipped in 000092 for HRM) to add `overtime_multiplier` (decimal 4,2 default 3.00) + `branch_id` (uuid nullable, reserved for per-region scoping) + `holidays_branch_idx`, and CREATES `calendar_events` (uuid PK, title, description, start_time, end_time, category enum, is_all_day, employee_id FK nullOnDelete, eventable_type/id reserved for future polymorphic, tenant_id index, softDeletes).
- [x] Extended `App\Models\Tenant\Holiday`: new fillable (`overtime_multiplier`, `branch_id`), `overtime_multiplier` decimal:2 cast, `isOnWeekend($year)` helper, `resolveDateForYear($year)` helper honoring `is_recurring`.
- [x] Created `App\Models\Tenant\CalendarEvent` (Auditable + SoftDeletes, UUID boot, category constants general/meeting/training/company/personal, `employee` relation + optional polymorphic `eventable` relation, `isPersonal()` helper).
- [x] Settings: `calendar.compensatory_day` (boolean, default true) + `calendar.default_overtime_multiplier` (string, default '3.00') appended to `SettingService::defaults()`.

---

## B. Backend Services and Logic (Phase 2 - Shipped)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.B, § 3*

- [x] **Calendar `HolidayService`** (new, layered over existing HRM HolidayService):
  - [x] `getCompensatoryDay(Holiday, ?year)` returns the Monday after a Sat/Sun holiday, gated on `calendar.compensatory_day` setting.
  - [x] `applicableHolidaysInRange($from, $to, ?$branchId)` delegates to HRM's `occurrencesInRange`, appends comp days, and filters by branch (null branch = applies everywhere).
  - [x] `checkIsHoliday($date, ?$branchId)` boolean helper for OvertimeService / attendance reconciler.
- [x] **`CalendarEventService`**:
  - [x] `create / update / destroy` for custom calendar_events with chronological-order guard (start <= end).
  - [x] `getCombinedEvents($from, $to, $filters)` unions custom events + holidays (with comp days) + leaves + employee_shifts + CRM appointments. Source tag on every event (`source: calendar|holiday|leave|shift|appointment`). Filters: categories (allowlist of sources), employee_id (cross-source self-scoping), branch_id.
  - [x] 90-day MAX_RANGE_DAYS guard + inverted-range guard; throws DomainException with explicit messages.
- [ ] **Privacy masking** in `CalendarEventResource` (Phase 3) - mask leave titles unless actor holds `hrm.leave.read`.
- [ ] **Attendance reconciler override** - deferred (not in v1 scope per user decision).

---

## C. API Layer and Access Policies (Phase 3 - Shipped)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 1, § 2.B*

- [x] Routes wired under `auth:api` inside `routes/tenant.php`: `GET/POST /calendar/events`, `GET/PUT/DELETE /calendar/events/{event}`. Holiday CRUD remains on the existing HRM endpoints (`/holidays`).
- [x] `CalendarEventController`: index = combined feed via `CalendarEventService::getCombinedEvents`; store/show/update/destroy for custom events. Self-scope callers (`calendar.event.read.self`) are forced to their own `employee_id` on index; new custom events are auto-attributed to the actor when they only hold `.write.self`.
- [x] `CalendarEventResource` applies privacy masking: leave titles fall back to "Leave - Confirmed" (description + employeeId hide) when the actor lacks `hrm.leave.read` and is not the owner; personal calendar events fall back to "Personal event" when the actor is neither the owner nor holds `calendar.event.read`.
- [x] `CalendarEventPolicy` registered in `TenantServiceProvider::boot` covering viewAny/view/create/update/delete with a `.self` fallback (`User::employee->id === CalendarEvent::employee_id`).
- [x] `CalendarPermissionSeeder` seeds 4 admin perms (`calendar.event.{read,write,delete,override}`) + 2 self perms (`.read.self`, `.write.self`); admin role gets the full set, employee role gets the `.self` variants. Wired into `TenantDatabaseSeeder` after `PosPermissionSeeder`.

---

## D. Frontend Page Scaffolding and Routing (Phase 4 - Shipped)
*Reference: [`skills/calendar/rules.md`](../../skills/calendar/rules.md) § 2.C*

- [x] `frontend/composables/useCalendar.ts` - typed `events.{list,show,create,update,destroy}` wrappers over `/calendar/events` + `sourceMeta(source)` helper returning `{label, icon, variant, dotClass}` for the 5 sources.
- [x] `frontend/pages/calendar/events.vue` - unified-events workspace with month grid, 5 source layer chips, event detail drawer, create/edit modal, day overview modal, upcoming agenda. Privacy masking inherited from `CalendarEventResource` on the backend.
- [x] Sidebar wire: "Unified Events" item added next to "My Calendar" in the Main group, gated on `calendar.event.read` OR `.read.self`, slug `calendar`.

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

## F. Integration and QA Testing (Phase 5 - Shipped)
*Reference: [`skills/calendar/testing.md`](../../skills/calendar/testing.md) § 1-3*

- [x] **`tests/Feature/Tenant/Calendar/CalendarTenancyIsolationTest.php`** (P0) - cross-tenant blindness for `calendar_events` + `holidays`; verifies the 6-permission Calendar catalog is seeded with admin holding the full set + employee holding only `.self` variants.
- [x] **`tests/Feature/Tenant/Calendar/HolidayCompensatoryDayTest.php`** (P1) - 7 cases: Saturday + Sunday holidays both produce Monday comp; weekday holiday produces no comp; `calendar.compensatory_day=false` suppresses generation; `applicableHolidaysInRange` appends comp rows inside window and drops them outside (with `compensatory_for` source id tagging); `checkIsHoliday` returns true on both original + comp Monday; recurring holiday resolves the comp-day per requested year (Jan 6 = Sat in 2024 -> comp Mon Jan 8; Jan 6 = Tue in 2026 -> no comp).
- [x] **`tests/Feature/Tenant/Calendar/CalendarPrivacyMaskingTest.php`** (P0) - `getCombinedEvents` projects leaves; `CalendarEventResource` masks leave title to "Leave - Confirmed" + hides description + employeeId when actor lacks `hrm.leave.read` and is not the owner; full detail shown when actor holds `hrm.leave.read`; owner always sees their own leave detail even without that permission; personal calendar events mask to "Personal event" for non-owners without `calendar.event.read`; range guard throws on inverted range + > 90 days.
- [ ] **Postman Collections Sync** - deferred to Phase 8.
