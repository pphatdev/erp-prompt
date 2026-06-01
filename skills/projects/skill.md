---
name: project-management
description: Project planning, WBS, Gantt timelines, Kanban task execution, resource allocation, and FMS-integrated time tracking.
---
# Project Management & Time Tracking

Use this skill when building, modifying, or extending features related to project planning, WBS structures, Gantt schedules, task assignments, timesheet logging, resource allocation, or project costing. This module processes operational metrics and financial integrations — all changes must maintain multi-tenant security and strict cost-accounting validation.

## Module Surface (Shipped & Planned)

```
Project Management (sidebar group)
├── Projects                       — CRUD, project budget details, members list
│   ├── Gantt Timeline             — Interactive visual schedule with dependencies
│   └── Budget vs Actual           — Financial tracking showing labor cost + expenses
├── Tasks & Kanban                 — Drag-and-drop board + tabular WBS lists
│   └── Task Detail Drawer         — Checklist, description, file uploads, inline comments
├── Time Tracking
│   ├── Log My Time (Self-Service) — Daily hours logger against assigned tasks
│   ├── My Timesheets              — Timesheet calendar + approval submission
│   └── Approvals (Manager View)   — Bulk timesheet approval dashboard
└── Resource Capacity              — Utilisation matrices and capacity planning heatmaps
```

| Layer | Path |
|---|---|
| **Controllers** | `app/Tenants/Modules/Projects/Controllers/{ProjectController, TaskController, TimesheetController}.php` |
| **Services** | `app/Tenants/Modules/Projects/Services/{ProjectService, TaskService}.php` |
| **Resources** | `app/Tenants/Modules/Projects/Resources/{ProjectResource, TaskResource, TimesheetResource}.php` |
| **Models** | `app/Models/Tenant/{Project, Task, Timesheet}.php` |
| **Policies** | `app/Policies/{Project, Task, Timesheet}Policy.php` |
| **Migrations** | `database/migrations/tenant/{date}_create_projects_tables.php` |
| **Seeder** | `TenantDatabaseSeeder.php` — seeds default task workflow statuses, sample projects, milestones, tasks, and timesheet logs. |
| **Pages** | `frontend/pages/projects/{index, [id], tasks, timesheets/index, timesheets/approvals, resource-capacity}.vue` |

---

## Permission Slug Catalog

```
projects.project.{read,write,delete}
projects.task.{read,write,delete}
projects.timesheet.{read,write,delete}
projects.timesheet.approve                ← Manager role to unlock bulk approval dashboard
projects.timesheet.read.self              ← Self-service: Read own logged hours only
projects.timesheet.write.self             ← Self-service: Log, edit, and delete own unsubmitted time logs
```

Self-service `.self` permissions are bound by resource policies: `TimesheetPolicy::view()` returns `true` if the user has `projects.timesheet.read` OR `($user->hasPermission('projects.timesheet.read.self') AND $user->employee_id === $row->employee_id)`.

---

## Critical Rules

### 1. Multi-Tenant Scoping (P0)
- **Database Separation**: Every query to `projects`, `tasks`, and `timesheets` must traverse the tenant connection initialized by the `InitializeTenancyByHandle` middleware. The central models utilize the `BelongsToTenant` trait.
- **Cross-Tenant Guarding**: An API call accessing `/api/v1/projects/{id}` or `/api/v1/tasks/{id}` must trigger standard model resolution scoped to the tenant. Attempting to update or read a project belonging to Tenant B from a Tenant A request context must fail with a `404 Not Found` (never a `403 Forbidden`, to prevent resource-existence scans).
- **Storage Isolation**: Task attachments are stored under a tenant-specific filesystem directory: `tenant_{handle}/projects/{project_uuid}/tasks/{task_uuid}/attachments/`. Uploads must validate MIME types (reject executables) and be served via short-lived signed URLs.

### 2. Gantt & Task Dependency Engine (P1)
- **Dependency Invariant**: Tasks support finishing relationships. A dependent task (`dependency_id`) cannot start until its parent task is completed.
- **Circular Reference Guard**: When linking task dependencies, the system must perform a depth-first search (DFS) through the dependency tree to prevent loops (e.g., Task A depends on Task B, which depends on Task A). Circular assignments must throw a `ValidationException` (422).
- **Timeline Shifting**: When a parent task's `due_date` shifts or is delayed, the `ProjectService::shiftTimeline($taskId, $daysDelayed)` must recursively recalculate and shift the start/due dates of all downstream dependent tasks, updating the Gantt timeline dynamically.

### 3. Strict Time Capture & Timesheet Locking (P1)
- **Hours Validation**: An employee cannot log more than 24 hours per calendar day. The daily limit is defined in tenant settings (default: 16 hours cap per employee to prevent logging anomalies).
- **Leave Overlap Check**: The `TaskService::logTime()` must query the HRM leave registry (`leaves`). It must reject timesheet submissions if the employee has an approved full-day leave on the targeted `log_date`.
- **Approval Lock State**: Timesheets exist in `draft`, `submitted`, `approved`, or `rejected` states.
  - **Draft/Rejected**: Editable and deletable by the employee via `.self` permissions.
  - **Submitted/Approved**: Read-only. Attempts to alter or delete an approved timesheet must trigger a `DomainException` (422: "Cannot modify locked or approved timesheets").
  - **Locking Boundary**: Standard timesheets lock automatically at the end of the weekly/monthly payroll period. This state is verified using the global `PayrollService::isPeriodLocked(Employee, Date)`.

### 4. Real-time Status Transitions & Broadcasting (P1)
- **WebSocket Channel Scoping**: Task movements on the Kanban board must broadcast status changes in real-time. The broadcast channel must be tenant-scoped and project-scoped: `private-tenant-{handle}.project-{project_uuid}`.
- **Auth Scoping**: Access to the project channel requires `projects.project.read` permission on the tenant, authorized via Laravel Passport in `routes/channels.php`.
- **Kanban Payload**: The broadcast event (`TaskStatusUpdated`) dispatches minimal data: `{ taskId, oldStatus, newStatus, assigneeId }` to trigger local state updates on other connected clients without heavy payload overhead.

### 5. Labor Costing & FMS Posting (P2)
- **Dynamic Cost Rate Resolution**: The hourly labor cost for a timesheet entry is calculated inside `ProjectService::calculateLaborCost()` using the following fallback cascade:
  1. **Task-Specific Rate**: Rate defined directly on the `task_assignee` pivot.
  2. **Project-Specific Rate**: Hourly cost rate defined in `project_members` for the employee.
  3. **HRM Default Rate**: Employee's `base_salary` converted to an hourly rate: `(base_salary / average_monthly_hours)` where average monthly hours are seeded as `160`.
- **FMS Invoicing Hook**: When timesheets are marked as `approved` and tagged as `billable`, they are registered as *unbilled inventory*. The FMS Billing engine pulls these records during invoice creation, updating them to `invoiced` and creating a journal posting: `DR Accounts Receivable (Client) / CR Project Revenue` mapped to the project ID.

---

## Status Flows (Workflow System Integration)

Consistent with global rules, project and task lifecycles resolve from the central `workflow_statuses` table through the `WorkflowStatusService`.

### Project Workflow States
| State Key | Initial/Terminal | Meaning | Action Trigger |
|---|:---:|---|---|
| `planning` | Initial | WBS construction, scoping, budgeting. | Project created. |
| `active` | Active | Active execution; time logs are enabled. | Project kicked off; team assigned. |
| `on_hold` | Active | Paused execution; time logging is blocked. | Manager pauses project due to budget/scope. |
| `completed` | Terminal | Project closed; all timesheets locked/billed. | Final budget variance calculated; archived. |

### Task Workflow States
| State Key | Initial/Terminal | Meaning | Action Trigger |
|---|:---:|---|---|
| `todo` | Initial | Backlog task awaiting assignment/start. | Created in WBS. |
| `in_progress` | Active | Work is active; timesheets allowed. | Assignee logs first hours or changes status. |
| `review` | Active | Under review; timesheets blocked. | Assignee submits task for validation. |
| `done` | Terminal | Completed; read-only. | Manager approves deliverable. |

---

## Frontend Integration Standards

- **Task Details Drawer**: Selecting a task on the Kanban board or Gantt chart opens a side drawer (`frontend/components/TaskDetailsDrawer.vue`) rather than a full page reload, maintaining workspace context.
- **PrimeVue Components**:
  - The Kanban board uses `DragDrop` modules styled to match the theme (`.glass-card`).
  - The WBS editor utilizes PrimeVue `TreeTable` to support fluid node expansion, nesting, and in-place inline editing of titles and dates.
- **Toast Confirmations**: Crucial actions (deleting projects, changing dependencies, locking timesheets) must execute through the unified `useConfirm()` and `useToast()` composables.

---

## Troubleshooting Matrix

| Symptom | Root Cause | Programmatic Resolution |
|---|---|---|
| **Timesheet log fails with 500** | Employee has an approved leave on the target date. | Add validation in `TaskService::logTime` throwing a custom `DomainException` to return 422: `Employee on approved leave`. |
| **Dependent tasks dates drift** | The date recalculation looped indefinitely. | Check the depth-first search circular dependency validator in `ProjectService`. Add cycle detection using visited-nodes tracking. |
| **Project budget variance is negative** | Labor cost rates are falling back to default $0.00. | Check the rate resolution fallback order. Ensure the `Employee` model exposes the unencrypted base salary only when `hrm.payroll.read` is present, falling back to a tenant default rate (e.g. `$50.00`) for managers without payroll access. |
| **Kanban doesn't sync across users** | Broadcast event routed to central rather than tenant-scoped channel. | Ensure the channel name in `TaskStatusUpdated.php` prepends the tenant handle: `tenant-{$this->tenantHandle}.project-{$this->projectId}`. |

---

## Read Next
- [`overview.md`](./overview.md) — Feature taxonomy, integrations, and concepts.
- [`rules.md`](./rules.md) — Permission matrices, DB schemas, and technical flows.
- [`flow.md`](./flow.md) — Visualizing WBS planning, task lifecycles, and billing flows.
- [`testing.md`](./testing.md) — Tenancy isolation, calculations, and QA test specs.
