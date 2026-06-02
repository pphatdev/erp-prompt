# Unified Calendar and Holiday Workflow Rules

This document specifies the concrete implementation standards, security protocols, database constraints, and business logic validation rules for the Unified Calendar and Holiday Management module.

---

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action` pattern. Access is restricted using standard Laravel Eloquent Policies.

### Permission Keys
- **Module**: `calendar`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix - Admin / Manager Scope
These permissions unlock full holiday configurations, roster shifts, scheduling overrides, and voiding posted sales.

| Feature | Read | Write | Delete | Special / Override |
|---|---|---|---|---|
| `holiday` | `calendar.holiday.read` | `calendar.holiday.write` | `calendar.holiday.delete` | - |
| `event` | `calendar.event.read` | `calendar.event.write` | `calendar.event.delete` | `calendar.event.override` (Adjust roster shifts) |

### Feature Matrix - Employee Scope (Self-Service)
Granted to standard employee roles. These scopes are enforced at the Eloquent policy level. The policy only returns true if the requested records belong to or are assigned to the authenticated user's linked `employee_id`.

| Permission | Endpoint(s) | Business Rules / Constraints |
|---|---|---|
| `calendar.event.read.self` | `GET /calendar/events` | User can only see their own active shift, holiday, and CRM meeting calendars. |
| `calendar.event.write.self`| `POST /calendar/swap-requests` | Cashier/Employee can submit shift transfer requests to peers. |

---

## 2. Implementation Standards

### A. Database Schema & Eloquent Relationships

The Calendar module utilizes two main tables defined in the tenant migration:

```
┌─────────────────────────────────┐
│            holidays             │
├─────────────────────────────────┤
│ id (UUID, PK)                   │
│ name (String)                   │
│ description (Text, Nullable)    │
│ date (Date)                     │
│ is_recurring (Boolean)          │
│ overtime_multiplier (Decimal)   │
│ branch_id (UUID, FK, Nullable)  │
│ tenant_id (String, Index)       │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│        calendar_events          │
├─────────────────────────────────┤
│ id (UUID, PK)                   │
│ title (String)                  │
│ description (Text, Nullable)    │
│ start_time (Timestamp)          │
│ end_time (Timestamp)            │
│ category (String)               │
│ is_all_day (Boolean)            │
│ eventable_type (String, Null)   │
│ eventable_id (UUID, Null)       │
│ employee_id (UUID, FK, Nullable)│
│ tenant_id (String, Index)       │
└─────────────────────────────────┘
```

#### Eloquent Model Invariants
1. **Primary Keys**: All models utilize UUID strings generated during the `creating` event.
2. **Tenancy isolation**: Models implement the `BelongsToTenant` trait. `Holiday` and `CalendarEvent` also utilize the `Auditable` trait and `SoftDeletes` for full tracking.
3. **Database Constraints**:
   - `holidays.date` carries an index to facilitate rapid range scans by the scheduling engine.
   - `calendar_events` uses a polymorphic structure (`eventable_type`, `eventable_id`) to map directly to leaves (`App\Models\Tenant\Leave`), attendance shifts, or CRM appointments.

---

### B. Backend (Laravel) Architecture

- **Namespace**: `App\Tenants\Modules\Calendar`
- **Routing**: Declared inside `routes/tenant.php` prefix `api/v1/calendar/`.
- **Services (Thick & Atomic)**:
  - `HolidayService.php`: Handles holiday creation, date shifts, and compensatory date overrides: `createHoliday()`, `getCompensatoryDay()`, `checkIsHoliday()`.
  - `CalendarEventService.php`: Aggregates operational calendars: `getCombinedEvents()`, `requestSwap()`.
  - **Transaction Enforcements**: All multi-row updates, parent-child cascades, and roster date changes must run inside a `DB::transaction()` block. If any step fails, the database rolls back completely.

---

### C. Frontend (Nuxt 3) Architecture

- **Path Mapping**: Pages live under `frontend/pages/calendar/`:
  - `frontend/pages/calendar/index.vue` (Unified visual calendar dashboard)
  - `frontend/pages/calendar/settings.vue` (Holiday config list for admins)
- **API Fetching**:
  - Direct fetch calls are prohibited. Always use `useApi()` to pass active `X-Tenant-Handle` header scopes.
- **PrimeVue Calendar Implementation**:
  - The calendar interface uses PrimeVue's full calendar system styled to match the dark glassmorphism theme (`.glass-card`).
  - Selecting an event displays detailed metadata inside a side panel rather than triggering a full page reload, maintaining viewport context.

---

## 3. Core Business Rules & Validations

### A. Overtime Pay Adjustments
- **Hourly Calculation**: If an employee works on a registered holiday, `OvertimeService` resolves the hourly cost multiplier based on the holiday settings:
  $$\text{Holiday Rate} = \text{Hourly Rate} \times \text{Overtime Multiplier}$$
  Failing to resolve the multiplier defaults the calculation to a standard $3.0\text{x}$ base multiplier.

### B. Compensatory Day Resolution
- **Rule**: If a registered holiday falls on a weekend date (Saturday or Sunday) and the setting `calendar.holiday.compensatory_day` is true:
  - The system automatically creates a virtual holiday entry on the next standard workday (Monday).
  - The attendance reconciler applies standard holiday status overrides to this compensatory day off.

### C. Privacy Masking Implementation
- **Eager Loading**: The calendar query eager-loads the polymorphics to determine leave metadata.
- **Conditional Serialization**: In `CalendarEventResource`, privacy masking logic checks permissions dynamically:
  ```php
  public function toArray(Request $request): array
  {
      $canSeeDetail = $request->user()?->can('hrm.leave.read') ?? false;

      return [
          'id' => $this->id,
          'title' => ($this->category === 'leave' && !$canSeeDetail) ? 'Leave - Confirmed' : $this->title,
          'startTime' => $this->start_time,
          'endTime' => $this->end_time,
          'category' => $this->category,
          'isAllDay' => $this->is_all_day,
      ];
  }
  ```
