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

## C. API Layer & Access Policies (Shipped 2026-06-01)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 1, § 2.B*

- [x] RESTful API routing inside `routes/tenant.php` prefix `api/v1` wrapped in tenant and auth middlewares.
- [x] Thin controllers `ProjectController`, `TaskController`, and `TimesheetController` with `Gate::authorize` on every action, validated payloads, and full apiResource coverage (index/store/show/update/destroy). Plus `GET /projects/kpis` powering the registry dashboard strip and the existing `GET /projects/{project}/budget-status` retained.
- [x] `ProjectResource`, `TaskResource`, `TimesheetResource` rewritten in camelCase with nested manager/assignee/employee snapshots (id, employeeId, fullName) and project/task cross-snapshots so the UI never needs to round-trip for display fields. `tasksCount` exposed when withCount loaded.
- [x] Index filters: ProjectController accepts `?search` (name+description), `?status`, `?manager_id`. TaskController accepts `?project_id`, `?status`, `?assignee_id`, `?priority`, `?search`. TimesheetController accepts `?task_id`, `?employee_id`, `?from`, `?to`.
- [x] Permission policies (`ProjectPolicy`, `TaskPolicy`, `TimesheetPolicy`) created with viewAny/view/create/update/delete and registered in `TenantServiceProvider`.
- [x] Permissions seeded in `TenantDatabaseSeeder`: `projects.project.{read,write,delete}`, `projects.task.{read,write,delete}`, `projects.timesheet.{read,write,delete}`.

---

## D. Frontend Page Scaffolding & Routing (Shipped 2026-06-01)
*Reference: [`skills/projects/rules.md`](../../skills/projects/rules.md) § 2.C*

- [x] `frontend/pages/projects/` folder scaffolded with `index.vue` shipped, `tasks/` and `timesheets/` pending.
- [x] Sidebar: `Project Management` flipped from leaf to group with children Projects (`ti-folder-open`), Tasks (`ti-checkbox`), Timesheets (`ti-clock-hour-3`). Module slugs `projects-overview`, `projects-tasks`, `projects-timesheets` seeded under the existing `projects` parent. Tasks and Timesheets children currently `operational: false` until their pages ship.
- [x] `useProjects` composable shipped at `frontend/composables/useProjects.ts` exposing `projects.{list,show,create,update,destroy,kpis,budgetStatus}`, `tasks.{list,show,create,update,destroy,updateStatus}`, `timesheets.{list,show,create,update,destroy}`. Type-safe via `frontend/types/projects.ts`. Pinia store skipped for now (composable proven sufficient by accounting modules).

---

## E. Frontend Workspaces & Views (Planned)
*Reference: [`skills/projects/overview.md`](../../skills/projects/overview.md) § 1-5*

### 1. Projects Registry Overview Page (`/projects`) (Shipped 2026-06-01)
- [x] **KPI Dashboard Strip**: 4 cards (Active, Over Budget, Unassigned Tasks, Hours This Month) with `useCountUp` animations. Backed by `GET /projects/kpis`.
- [x] **Project Cards Grid**: Glass-card grid (1/2/3 columns responsive). Each card surfaces name, manager fullName, date range, status badge, budget, tasks count. Card click routes to `/projects/[id]`. Edit + Delete actions inline on each card (gated by perms).
- [-] **Registry Table View**: PrimeVue DataTable toggle skipped for v1; card grid is the default. Will reconsider when admin bulk-export is needed.
- [x] **Create/Edit Project Modal**: Name, description, dates, budget, status select, manager picker (lazy from `/employees?limit=200`). Status select uses PROJECT_STATUSES from useProjects.

### 2. Project Detail & WBS Page (`/projects/[id]`) (Shipped 2026-06-01)
- [x] **Tabbed Details Workspace**: 2 tabs shipped (Tasks + Budget). Timeline/Gantt deferred to v2 since the task schema has no `parent_id` or dependency edges to wire it up.
- [-] **Hierarchical WBS List (TreeTable)**: Deferred to v2 since the current `tasks` table has no `parent_id` column. v1 ships a flat task table with inline status select, priority badge, assignee, due-date (overdue tinted), edit/delete actions. Status changes hit `PATCH /tasks/{id}/status` with optimistic UI (revert on failure).
- [-] **Gantt Scheduler**: Deferred to v2, depends on the WBS dependency model being introduced first (cycle prevention + date cascade work in section B). The flat task list serves the v1 ship.
- [x] **Header workspace**: Project name + status badge + date range + manager. Stat strip (4 cards: tasks count + open/done split, in-progress + todo/review split, budget, variance). Edit Project + Delete actions inline.
- [x] **Budget tab**: Calls `GET /projects/{id}/budget-status`. 4-card breakdown (Budget / Actual Cost / Variance / Used %) plus a burn progress bar tinted green/amber/red by usage. Notes the flat-50/hr placeholder; v2 will swap in per-assignee rates via the cost-rate fallback resolver (section B P2).
- [x] **Task modal**: Create + Edit reuse the same modal. Title, description, status select (TASK_STATUSES), priority select (TASK_PRIORITIES), assignee picker (lazy from `/employees?limit=200`), due date. Project_id is pre-bound to the route param.
- [x] **Inline status select**: Each task row exposes a native select wired to `pm.tasks.updateStatus`. Optimistic update with rollback on failure. Invalidates budget cache so the next Budget tab refresh recomputes actuals.

### 3. Kanban Task Execution Board (`/projects/[id]/kanban`) (Shipped 2026-06-01)
- [x] **Drag-and-Drop Columns**: 4 columns (Todo / In Progress / Review / Done) using native HTML5 DnD API (not PrimeVue, since the codebase defaults to custom Tailwind chrome). Each column tracks a hover state via `dragOverColumn` for a tinted drop target. Tasks grouped by status via a `tasksByStatus` computed off the loaded task array.
- [x] **Optimistic State Updates**: On drop, the card's `t.status` flips client-side immediately, the card id is added to a `pendingIds` set which dims the card and disables further drags, the page fires `PATCH /tasks/{id}/status`, and on success removes the pending flag with a "Moved" toast. On failure, status reverts to its previous value and a "Move failed" toast surfaces the API message. Tasks that are already pending cannot be re-dragged.
- [-] **Task Details Side Drawer (checklist + comments + attachments)**: Deferred to v2. Current backend has no `task_checklist_items`, no polymorphic comments table for tasks, and no `task_attachments`. v1 ships a click-to-open Edit Task modal (title, description, status, priority, assignee, due date) plus an inline Delete button inside the modal footer so the click-card flow stays one click for the common edit/move/delete cases.
- [x] **Header chrome**: Back-link to `/projects/[id]`, project title, priority + assignee filters (auto-reload via `watch`), New Task button. Project detail header now also surfaces a Kanban button that navigates to the board.

### 4. Time Tracking Self-Service & Approvals (`/projects/timesheets`) (Shipped 2026-06-01)
- [x] **My Logs Portal** (`/projects/timesheets`): Self-service page targeting the current employee (resolved via `GET /employees/me`). Date-range filter defaults to the current Sun-Sat week with Prev/Today/Next buttons that re-anchor the range and reload. KPI strip (Total Hours / Entries / Distinct Tasks / Daily Avg with `useCountUp` animations). Entries grouped by day in descending date order; each day header shows weekday + total hours (tinted amber > 8h, red > 16h to surface the 16h/day cap that section B P1 will enforce server-side). Per-entry rows show task title, project link to `/projects/[id]`, notes preview, hours, and edit/delete actions. Log Time modal: task picker showing "Project / Task" composite labels (lazy `pm.tasks.list({limit:500})`), date, hours (max 24h client-side matching the backend validation), notes.
- [-] **Draft / Submit Weekly Timesheet workflow**: Deferred to v2. The current `timesheets` table has no `status` column or `submitted_at`/`approved_at`/`approved_by` audit fields. Adding the lock flow needs a migration plus backend transitions; entries currently save immediately and are editable in place.
- [-] **Manager Approval Board** (`/projects/timesheets/approvals`): Deferred to v2 alongside the draft/submit workflow above. Once entries have a status enum (draft -> submitted -> approved | rejected), the approval board surfaces submitted entries grouped by employee with bulk approve + per-entry reject-with-feedback. Out of scope until the migration lands.
- [x] **Sidebar**: Timesheets entry flipped `operational: true` so the link is clickable. The `excludePrefixes` machinery added during the "fix active Tasks page" pass keeps `Projects` from also lighting up when `/projects/timesheets` is the active route.

---

## F. Integration & QA Testing (Planned)
*Reference: [`skills/projects/testing.md`](../../skills/projects/testing.md) § 1-4*

- [ ] **Backend Pest Test Suite**:
  - [ ] Write `TenancyIsolationTest` asserting cross-tenant resource reads are blocked with a `404 Not Found`.
  - [ ] Write `WbsDependencyTest` validating that cycles (DFS) return `422` and that timeline drifts successfully shift dependent tasks.
  - [ ] Write `TimesheetValidationTest` verifying hour caps, approved leave blocks, and payroll locks.
- [ ] **Postman Collections Sync**:
  - [ ] Add project/task creation and timesheet log scenarios into `docs/postman/erp_collection.json`.
