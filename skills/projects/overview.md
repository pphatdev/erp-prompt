# Feature: Project Management & Time Tracking

## Overview

The **Project Management** module is the central engine for organizing, tracking, and executing client-facing or internal projects across a multi-tenant enterprise. It provides tools to structure work into hierarchical Work Breakdown Structures (WBS), track timelines visually via Gantt charts, manage workloads dynamically on Kanban boards, allocate human resources according to capacity, log billable and non-billable time, and monitor budget vs. actual cost variances in real-time.

Crucially, Project Management acts as an **operational bridge** connecting **Human Resource Management (HRM)** and **Financial Management (FMS)**. It links directly to the employee registry for assignments and rates, and feeds billable logs directly into the FMS invoice generation and general ledger ledger entry flows.

---

## Module Taxonomy

The Project Management menu surface (top-level sidebar group `projects`) groups all project-lifecycle and scheduling activities into six main functional areas:

### 1. Project Planning & Work Breakdown Structure (WBS)
The strategic planning workspace.
- **Projects Registry** — CRUD operations for projects, defining stakeholders, start/end dates, base billing rates, and default budget allocations.
- **WBS & Milestones** — Hierarchical task tree decomposing projects into phases, milestones (gates), and specific executable tasks.
- **Gantt Timeline** — Interactive chronological scheduler displaying tasks, durations, and hard dependencies (`finish-to-start`).

### 2. Task Management & Kanban Board
The tactical execution workspace.
- **Kanban Board** — Drag-and-drop workspace displaying task cards sorted by workflow status (`todo`, `in_progress`, `review`, `done`), styled with PrimeVue drag-and-drop.
- **Task List View** — Tabular, filterable view with rich sorting by priority (`low`, `medium`, `high`, `urgent`), due date, assignee, and completion percentage.
- **Task Details Modal** — Polymorphic workspace containing detailed instructions, custom checklists, assignee details, inline collaboration threads, and file attachments.

### 3. Resource Allocation & Capacity Planning
The resource utility workspace.
- **Resource Allocator** — Matrix mapping active employees to project tasks based on skill tagging and availability calendars.
- **Workload & Capacity View** — Visual indicator (heat map) of employee utilization to prevent over-allocation (e.g., exceeding 40 hours/week) across all active tenant projects.
- **Employee Assignment Rates** — Custom hourly rates (cost rate and billable rate) per employee-project connection, overriding default global rates.

### 4. Time Tracking & Timesheets
The labor capture workspace.
- **Daily Time Logger** — Self-service utility allowing employees to log exact decimal hours (`hours_worked`) against assigned tasks.
- **Timesheet Calendar** — Calendar view for employees to review historical entries, submit timesheets for weekly approval, and view rejection feedback.
- **Approval Board** — Managerial dashboard to review, approve, or reject pending timesheet records before they are locked for payroll and billing.

### 5. Collaboration & Activity Stream
The communications workspace.
- **Activity Feed** — Chronological audit trail of all project events (status changes, assignee handoffs, priority escalations).
- **Inline Task Comments** — Rich-text comment threads nested inside task views, with `@mentions` driving real-time notifications.
- **Document Attachments** — Direct file uploading to task cards, storing files securely inside the tenant's isolated, encrypted filesystem folder.

### 6. Analytics & Financial Controls
The monitoring and cost-containment workspace.
- **Budget vs. Actual Dashboard** — Real-time tracking of planned budgets against actual costs (derived from logged timesheet hours multiplied by employee cost rates + project expenses).
- **Burn-Up/Burn-Down Charts** — Projected completion vectors based on historical velocity and outstanding backlog story points.
- **Profitability Analyzer** — Client-level financial lens comparing total billable timesheet value against actual resource cost to calculate project margin.

---

## Cross-Module Integration Contract

Project Management relies heavily on and links with core modules rather than duplicating master records:

```
                  ┌──────────────────────────────┐
                  │      HRM (Employee Data)     │
                  │   - Active Employees         │
                  │   - Cost/Hourly Salary Rates │
                  └──────────────┬───────────────┘
                                 │ Links Assignees & Rates
                                 ▼
┌────────────────────────────────────────────────────────┐
│               PROJECT MANAGEMENT MODULE                │
│   - Gantt & WBS Timeline      - Time Tracking Logs     │
│   - Kanban Board Execution    - Budget vs Actual Cost  │
└────────────────────────┬───────────────────────────────┘
                         │ Posts Billable Logs & Costs
                         ▼
                  ┌──────────────────────────────┐
                  │   FMS (Financials & Billing) │
                  │   - Client Invoicing (AR)    │
                  │   - Expense Postings         │
                  │   - Balanced Journal Entries │
                  └──────────────────────────────┘
```

1. **HRM (Workforce)**:
   - Assignees are validated against active `employees` records.
   - Timesheets must check whether the employee is currently active and not on approved leave (`leaves.status = approved`) during the log date.
   - Resource cost calculations pull the employee's default wage from the HRM payroll system (`base_salary` converted to an hourly equivalent) if no project-specific rate is set.

2. **FMS (Finance)**:
   - Approved, billable timesheet logs are queried by the FMS billing system to auto-generate client invoices, moving them from `uninvoiced` to `invoiced`.
   - Budget calculations query FMS expense records posted under the project's unique tracking dimension (`project_id`).
   - Closing or completing a billable milestone dispatches a balanced GL journal entry posting (`DR Unbilled AR / CR Project Revenue`).
