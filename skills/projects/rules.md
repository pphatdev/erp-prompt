# Project Management Workflow Rules

This document specifies the concrete implementation standards, security protocols, database constraints, and business logic validation rules for the Project Management and Time Tracking module.

---

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action` pattern. Access is restricted using standard Laravel Eloquent Policies.

### Permission Keys
- **Module**: `projects`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin / Manager Scope
These permissions unlock full administrative APIs, project budgets, employee lists, and editing capabilities for any project under the active tenant connection.

| Feature | Read | Write | Delete | Export / Bulk |
|---|---|---|---|---|
| `project` | `projects.project.read` | `projects.project.write` | `projects.project.delete` | `projects.project.export` |
| `task` | `projects.task.read` | `projects.task.write` | `projects.task.delete` | `projects.task.export` |
| `timesheet` | `projects.timesheet.read` | `projects.timesheet.write` | `projects.timesheet.delete` | `projects.timesheet.approve` |
| `resource` | `projects.resource.read` | `projects.resource.write` | - | `projects.resource.export` |

### Feature Matrix — `.self` Scope (Self-Service / Regular Employee)
Granted to the standard employee roles. These scopes are enforced at the Eloquent policy level. The policy only returns `true` if the requested records belong to or are assigned to the authenticated user's linked `employee_id`.

| Permission | Endpoint(s) | Business Rules / Constraints |
|---|---|---|
| `projects.project.read.self` | `GET /projects`, `GET /projects/{id}` | User can only see projects where they are added as a project member or manager. |
| `projects.task.read.self` | `GET /tasks`, `GET /tasks/{id}` | User can only view tasks assigned to them or created by them. |
| `projects.task.write.self` | `PATCH /tasks/{id}/status` | Limited to updating `status` (moving from `todo` to `in_progress` or `review`). Cannot edit description, due date, budget, or assignee. |
| `projects.timesheet.read.self` | `GET /timesheets`, `GET /timesheets/{id}` | List endpoints strictly filter and return only timesheet logs belonging to the user's `employee_id`. |
| `projects.timesheet.write.self`| `POST /timesheets`, `PATCH /timesheets/{id}`, `DELETE /timesheets/{id}` | Allowed only if the timesheet `status` is `draft` or `rejected`, and the target `employee_id` matches the user. Locks immediately upon submission. |

---

## 2. Implementation Standards

### A. Database Schema & Eloquent Relationships

The module utilizes three main tables defined in the tenant migration:

```
┌─────────────────────────────────┐
│            projects             │
├─────────────────────────────────┤
│ id (UUID, PK)                   │
│ name (String)                   │
│ description (Text, Nullable)    │
│ start_date (Date, Nullable)     │
│ end_date (Date, Nullable)       │
│ budget (Decimal 15,2)           │
│ status (String)                 │
│ manager_id (UUID, FK, Nullable) ├────────┐
│ tenant_id (String, Index)       │        │
└────────────────┬────────────────┘        │
                 │                         │
                 │ 1                       │
                 │                         │
                 │ N                       │
┌────────────────▼────────────────┐        │
│             tasks               │        │
├─────────────────────────────────┤        │
│ id (UUID, PK)                   │        │
│ project_id (UUID, FK)           │        │
│ title (String)                  │        │
│ description (Text, Nullable)    │        │
│ due_date (Date, Nullable)       │        │
│ status (String)                 │        │
│ priority (String)               │        │
│ assignee_id (UUID, FK, Nullable)├─────┐  │
│ tenant_id (String, Index)       │     │  │
└────────────────┬────────────────┘     │  │
                 │                      │  │
                 │ 1                    │  │
                 │                      │  │
                 │ N                    │  │
┌────────────────▼────────────────┐     │  │
│           timesheets            │     │  │
├─────────────────────────────────┤     │  │
│ id (UUID, PK)                   │     │  │
│ task_id (UUID, FK)              │     │  │
│ employee_id (UUID, FK)          ├─────┼──┘
│ log_date (Date)                 │     │
│ hours_worked (Decimal 5,2)      │     │
│ notes (Text, Nullable)          │     │
│ tenant_id (String, Index)       │     │
└─────────────────────────────────┘     │
                                        │
┌───────────────────────────────────────▼┐
│          employees (HRM)              │
├───────────────────────────────────────┤
│ id (UUID, PK)                         │
└───────────────────────────────────────┘
```

#### Eloquent Model Invariants
1. **Primary Keys**: All models (`Project`, `Task`, `Timesheet`) utilize UUIDs (`$keyType = 'string'`, `$incrementing = false`) generated during the model `creating` event.
2. **Traits**: Models utilize `BelongsToTenant` for automatic database tenancy scoping. `Project` and `Task` also implement the `Auditable` trait and `SoftDeletes`.
3. **Foreign Keys**:
   - `projects.manager_id` references `employees.id` on delete set null.
   - `tasks.project_id` references `projects.id` on delete cascade.
   - `tasks.assignee_id` references `employees.id` on delete set null.
   - `timesheets.task_id` references `tasks.id` on delete cascade.
   - `timesheets.employee_id` references `employees.id` on delete cascade.

---

### B. Backend (Laravel) Architecture

- **Namespace**: `App\Tenants\Modules\Projects`
- **Routing**: Declared inside `routes/tenant.php` under the Project Management segment.
  - Endpoints prefix: `api/v1/`
  - Middleware group: `['api', InitializeTenancyByHandle::class, 'auth:api']`
- **Controllers (Thin)**: Handles input validation via `FormRequest` classes, delegates action execution to the Service layer, and returns the response using Eloquent Resources directly.
- **Service Layer (Thick & Atomic)**:
  - `ProjectService.php` contains core methods: `createProject()`, `getBudgetStatus()`, `shiftTimeline()`, `validateDependencies()`.
  - `TaskService.php` contains core methods: `logTime()`, `updateStatus()`, `verifyTimesheetLimits()`.
  - **Transaction Enforcements**: All multi-row updates, parent-child cascades, and date recalculations must be wrapped inside a `DB::transaction()` block to guarantee database atomicity.

#### Date Scheduling Validation Contract
When adding or updating a task's dates:
1. **Project Bounds Check**: A task's `due_date` must not fall before the parent project's `start_date` or after the project's `end_date` (if defined). Violations must throw a `ValidationException` (422).
2. **Dependency Gap Invariant**: If Task B depends on Task A, Task B's start date cannot be scheduled before Task A's `due_date`. The gap between Task A's completion and Task B's start must satisfy `gap >= 0`.
3. **Recalculation Sequence**:
   - When Task A's `due_date` shifts forward by `X` days, the system triggers `ProjectService::shiftTimeline($taskId, $days)`.
   - The service queries all tasks that depend directly on Task A.
   - It updates their start/due dates by `X` days and recursively propagates this shift downstream.
   - This date cascade operates inside a database transaction to prevent partial rescheduling states in case of validation failures.

---

### C. Frontend (Nuxt 3) Architecture

- **Path Mapping**: Pages live under `frontend/pages/projects/` organized by resource path. There is no `src/modules/` or custom module folder.
  - `/projects` maps to `frontend/pages/projects/index.vue`
  - `/projects/{id}` maps to `frontend/pages/projects/[id].vue`
  - `/projects/timesheets` maps to `frontend/pages/projects/timesheets/index.vue`
- **API Call Invariant**: Every request must navigate through `useApi()`. This ensures that the active tenant header (`X-Tenant-Handle`) and Passport JWT (`Authorization: Bearer <token>`) are automatically injected. Direct use of `$fetch` is strictly prohibited.
- **Kanban Drag-and-Drop Implementation**:
  - The Kanban board uses PrimeVue's drag-and-drop system.
  - When a task card is dropped into a new column, the frontend issues a single non-blocking `PATCH /api/v1/tasks/{id}/status` request with `{ status: 'new_status' }`.
  - The UI updates locally instantly (optimistic updates), but displays a subtle spinner on the card until the server returns a `200 OK`. If the server returns an error (e.g., 422 for a locked task), the card transitions back to its original column and a warning toast is fired via `useToast()`.

---

## 3. Core Business Rules & Validations

### A. Timesheet Logging Rules
- **Maximum Daily Limit**: Daily time logged by a single employee across all tasks must not exceed `16` hours.
- **Active Assignment Rule**: If the project's `restrict_assignments` setting is enabled, only employees registered as project members (`project_members` table) can log time against tasks. If disabled, any active tenant employee can log time.
- **Date Check**: Hours cannot be logged against a future date. The `log_date` must satisfy `log_date <= now()`.
- **Approved Status Lock**: Once a manager approves a timesheet row:
  - The row's `status` changes to `approved`.
  - The row is locked: subsequent `UPDATE` or `DELETE` queries on this row must fail immediately with a `DomainException` (422).
  - Reversing an approved timesheet is restricted to managers with `projects.timesheet.approve` privileges, which returns the status to `draft`.

### B. Project Budgeting & Variance Job
- **Scheduled Reconciliation**: The system runs a scheduled cron job (`ProjectCostJob.php`) nightly to calculate actual project costs.
- **Formula**:
  $$\text{Actual Cost} = \sum (\text{Timesheet Hours} \times \text{Employee Hourly Cost Rate}) + \text{Direct FMS Project Expenses}$$
- **Variance Formula**:
  $$\text{Budget Variance} = \text{Budgeted Amount} - \text{Actual Cost}$$
- **Alert Flags**: If `Actual Cost > Budgeted Amount`, the project is flagged as `over_budget`. The system dispatches real-time alerts to the project manager's dashboard and logs an audit trail record detailing the cost overrun.

---

## 4. Developer Coding & Debugging Guidelines (Gotchas & Run-Time Issues)

When writing, extending, or maintaining code inside the Project Management module, developers must actively avoid these common architectural, database, and integration pitfalls.

### A. UUID Typecasting & Relationship Traps
> [!CAUTION]
> **Symptom**: Relationships return `null` or Eloquent returns integer `0` for primary/foreign keys instead of string UUIDs.
- **Root Cause**: Laravel's default model setting assumes an auto-incrementing integer key.
- **Developer Rule**: Every new Eloquent model representing a tenant-scoped resource (`Project`, `Task`, `Timesheet`) must explicitly declare the key structure:
  ```php
  protected $keyType = 'string';
  public $incrementing = false;
  ```
  Failing to set these properties causes Eloquent to cast primary keys and relationship foreign keys (like `project_id` or `task_id`) to integer representation (`0`), breaking all model serialization and database querying.

### B. Bypassing the Audit Trail (Event Traps)
> [!IMPORTANT]
> **Symptom**: Critical budget updates, status transitions, or assignee handoffs occur without leaving entries in the `audit_logs` table.
- **Root Cause**: Utilizing raw database queries (`DB::table(...)`) or mass Eloquent queries (e.g., `Task::where(...)->update(...)`) bypasses Laravel's Eloquent lifecycle events (`saving`, `updating`, `deleting`).
- **Developer Rule**: Never use mass updates or raw query builders for state-changing operations. The `Auditable` trait relies entirely on standard Eloquent model events. Developers must retrieve the model instances and call `update()` or `save()` on individual models:
  ```php
  // WRONG: Bypasses lifecycle events and audit trail
  Task::where('project_id', $id)->update(['status' => 'archived']);

  // CORRECT: Standard events fire, recording who changed what
  Task::where('project_id', $id)->get()->each(fn ($task) => $task->update(['status' => 'archived']));
  ```

### C. Downstream Date recasting & Cascade Failures
> [!WARNING]
> **Symptom**: Recalculating task timelines due to dependency delays crashes, leaving half the Gantt schedule updated and the rest out of sync.
- **Root Cause**: Performing recursive updates on database columns outside of a database transaction. If a downstream task fails validation (e.g., date drifts outside of project bounds), the database does not roll back the upstream dates.
- **Developer Rule**: Date recalculation logic inside `ProjectService::shiftTimeline()` must be fully isolated inside a `DB::transaction()` block. If any task triggers a validation failure, the block catches it, rolls back all scheduled changes, and returns a clean error to the frontend.
  ```php
  public function shiftTimeline(string $taskId, int $daysDelayed): void
  {
      DB::transaction(function () use ($taskId, $daysDelayed) {
          $this->propagateTimelineShift(Task::findOrFail($taskId), $daysDelayed);
      });
  }
  ```

### D. WebSocket Handshake Failure (Echo Auth 403)
> [!NOTE]
> **Symptom**: Dragging a Kanban card successfully updates the database but fails to broadcast to other users. The browser console throws a `403 Forbidden` error on `POST /broadcasting/auth`.
- **Root Cause**: The WebSocket authorization handshake fails because the client-side Laravel Echo instance did not provide the active tenant handle, causing the server to authorize the request on the wrong central database context.
- **Developer Rule**: In `frontend/plugins/socket.ts`, ensure the Echo configuration explicitly passes the `X-Tenant-Handle` header inside the auth endpoint options:
  ```typescript
  import Echo from 'laravel-echo';

  const echoInstance = new Echo({
      broadcaster: 'reverb',
      key: runtimeConfig.public.reverbKey,
      wsHost: runtimeConfig.public.reverbHost,
      wsPort: runtimeConfig.public.reverbPort,
      wssPort: runtimeConfig.public.reverbPort,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/v1/broadcasting/auth',
      auth: {
          headers: {
              'X-Tenant-Handle': tenantStore.activeHandle, // MANDATORY: Switches database context for auth
              'Authorization': `Bearer ${authStore.token}`
          }
      }
  });
  ```

### E. Tenant Scope Leakage (BelongsToTenant Trait)
> [!CAUTION]
> **Symptom**: A query for `Task::all()` or `Timesheet::all()` returns records belonging to multiple different tenants.
- **Root Cause**: Forgetting to apply the `BelongsToTenant` trait to a model.
- **Developer Rule**: Every Eloquent model operating within the tenant-space database schema MUST import and use `App\Models\Traits\BelongsToTenant`. This trait registers a global query scope that automatically locks all SQL reads and writes to `tenant_id = current_tenant_handle`, ensuring complete isolation under Stancl Tenancy.

### F. Seeding and Mock Data Failures
> [!IMPORTANT]
> **Symptom**: Running `php artisan tenants:seed` crashes with missing relationship constraints or null references on workflow states.
- **Root Cause**: Seeders trying to create tasks before projects, or calling `WorkflowStatusService` when the lookup table is empty.
- **Developer Rule**: Seeding order must follow strict structural dependency hierarchy:
  1. `WorkflowStatusSeeder` (must seed task states like `todo`, `in_progress`, `review`, `done` first).
  2. `EmployeeSeeder` (must seed managers and assignees).
  3. `ProjectSeeder` (must seed projects).
  4. `TaskSeeder` (must seed WBS task trees).
  5. `TimesheetSeeder` (must seed daily log entries last).
  Always wrap seeds in defensive checks (e.g., `if (Project::count() === 0)`) to ensure the seed is fully idempotent.

