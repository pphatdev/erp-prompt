# Task Checklist: Project Management & Time Tracking

> See [`skills/projects/skill.md`](../../skills/projects/skill.md) for the canonical Project Management scope. This module is the execution engine for tracking WBS timelines, resource utilization, and time log validations, integrating operational metrics directly with **HRM** (Workforce) and **FMS** (Finance).

Legend: ✅ shipped · ◐ partial · ⬜ planned

---

## A. Core Schema & Models (Shipped)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 2.A*

- [x] Multi-tenant migrations for `projects`, `tasks`, and `timesheets` (`2024_01_01_000014_create_projects_tables.php`).
- [x] UUID primary key generation in model `creating` boot hooks (with incrementing disabled and keyType set to string).
- [x] **Tenancy Isolation Scope** — import and verify `BelongsToTenant` on `Project`, `Task`, and `Timesheet` models.
- [x] **Auditing Trail Hooks** — import and wire the `Auditable` and `SoftDeletes` traits on the `Project` and `Task` models.
- [x] **Model Relationship Scaffolding**:
  - `Project` belongs to `manager` (Employee) and has many `tasks`.
  - `Task` belongs to `project`, `assignee` (Employee), and has many `timesheets`.
  - `Timesheet` belongs to `task` and `employee`.

---

## B. Backend Services & Business Logic (Partial)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 2.B, § 3*

- [x] `ProjectService::createProject` wrapped inside database transaction.
- [x] `TaskService::updateStatus` for simple status changes.
- [x] `TaskService::logTime` executing initial hours log, creating a `timesheet` record, and automatically promoting status to `in_progress`.
- [ ] **P1 - Dependency Cycle Prevention**:
  - [ ] Implement Depth-First Search (DFS) cycle-detection in `ProjectService::validateDependencies`.
  - [ ] Throw `ValidationException` (422: "Circular dependency loop detected") when Task B is set to depend on Task A while Task A depends on Task B.
- [ ] **P1 - Date Recalculation Cascade**:
  - [ ] Implement `ProjectService::shiftTimeline($taskId, $daysDelayed)` wrapped inside `DB::transaction()`.
  - [ ] Propagate date changes downstream to all dependent tasks, shifting `start_date` and `due_date` correspondingly.
  - [ ] Enforce bounds validation ensuring no task shifts past the project's own `end_date`.
- [ ] **P1 - Time Capture & Leave Validations**:
  - [ ] Implement 16-hour daily logging limit validation in `TaskService::logTime` across all projects.
  - [ ] Query HRM leave records to reject logging when employee has an `approved` leave on `log_date`.
  - [ ] Verify if the target monthly period is locked in the HRM/FMS registry (`PayrollService::isPeriodLocked()`), blocking updates if locked.
- [ ] **P2 - cost Rate Fallback Resolver**:
  - [ ] Implement `ProjectService::calculateLaborCost()` traversing fallback cascade: pivot assignee rate $\rightarrow$ project member rate $\rightarrow$ HRM converted salary rate (base / 160h).

---

## C. API Layer & Access Policies (Shipped)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 1, § 2.B*

- [x] RESTful API routing inside `routes/tenant.php` prefix `api/v1` wrapped in tenant and auth middlewares.
- [x] Thin controllers `ProjectController`, `TaskController`, and `TimesheetController` converting inputs and returning JSON resources.
- [x] `ProjectResource`, `TaskResource`, and `TimesheetResource` serialization converting snake_case database columns to camelCase JSON envelopes.
- [x] Permission policies (`ProjectPolicy`, `TaskPolicy`, `TimesheetPolicy`) registered in `TenantServiceProvider` and enforced on controllers.
- [x] Seeds standard permissions (`projects.project.*`, `projects.task.*`, `projects.timesheet.*`) in `TenantDatabaseSeeder`.

---

## D. Frontend Page Scaffolding & Routing (Planned)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 2.C*

- [ ] Scaffold folder structure flatly inside Nuxt: `frontend/pages/projects/`.
- [ ] Register navigation routes and icons (`ti-folder-open`) inside the sidebar configuration gating on `projects.project.read` permission.
- [ ] Define the `useProjects` composable (`frontend/composables/useProjects.ts`) and project Pinia state store (`frontend/stores/projects.ts`).

---

## E. Frontend Workspaces & Views (Planned)
*Reference: [`skills/projects/overview.md`](../../skills/projects/overview.md) § 1-5*

### 1. Projects Registry Overview Page (`/projects`)
- [ ] **KPI Dashboard Strip**: Render metric boxes (total active projects, over-budget warning count, unassigned tasks, logged hours this month) using `useCountUp` animations.
- [ ] **Project Cards Grid**: Render premium HSL-colored glass-cards detailing project name, manager name, start/due dates, progress bar, and budget status badge.
- [ ] **Registry Table View**: Toggleable PrimeVue DataTable for administrative sorting, filtering, and bulk exports.
- [ ] **Create/Edit Project Modal**: Dialog with fields for name, description, budget, manager picker (lazy loading `/employees`), and dates.

### 2. Project Detail & WBS Page (`/projects/[id]`)
- [ ] **Tabbed Details Workspace**: Tabs for Timeline (Gantt), Task List (WBS), and Budget actuals.
- [ ] **Hierarchical WBS List**: Utilize PrimeVue `TreeTable` to support nested task expansion, showing status, assignee, priority, and inline date editing.
- [ ] **Gantt Scheduler**: Build chronologically synced canvas/SVG timeline mapping task bars and routing dependencies (Finish-to-Start). Date drag-and-drop triggers timeline shifts.

### 3. Kanban Task Execution Board (`/projects/[id]/tasks` or tab)
- [ ] **Drag-and-Drop Columns**: PrimeVue drag-and-drop interface splitting tasks by status: `todo`, `in_progress`, `review`, and `done`.
- [ ] **Optimistic State Updates**: Card displays spinner and dims on drop, unlocking and solidifying upon `200 OK` return. Drops back on failure with warning toast.
- [ ] **Task Details Side Drawer**: Slide-out panel (`TaskDetailsDrawer.vue`) triggered on card click, housing:
  - Checklist item manager (add, toggle, remove sub-items).
  - Inline polymorphic comments section with real-time text thread additions.
  - File attachments drop zone (uploading securely to tenant path, showing image previews).

### 4. Time Tracking Self-Service & Approvals (`/projects/timesheets`)
- [ ] **My Logs Portal** (`/projects/timesheets`): Calendar workspace where employees enter hours against tasks, see draft logs, and click "Submit Weekly Timesheet" to lock hours.
- [ ] **Manager Approval Board** (`/projects/timesheets/approvals`): Grid for managers to review submitted logs, display totals, bulk approve, or input feedback on rejection.

---

## F. Integration & QA Testing (Planned)
*Reference: [`skills/projects/testing.md`](../../skills/projects/testing.md) § 1-4*

- [ ] **Backend Pest Test Suite**:
  - [ ] Write `TenancyIsolationTest` asserting cross-tenant resource reads are blocked with a `404 Not Found`.
  - [ ] Write `WbsDependencyTest` validating that cycles (DFS) return `422` and that timeline drifts successfully shift dependent tasks.
  - [ ] Write `TimesheetValidationTest` verifying hour caps, approved leave blocks, and payroll locks.
- [ ] **Postman Collections Sync**:
  - [ ] Add project/task creation and timesheet log scenarios into `docs/postman/erp_collection.json`.
