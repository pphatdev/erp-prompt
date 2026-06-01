# Project Management Workflows

This document maps the operational lifecycles, real-time sync systems, and financial posting pipelines of the Project Management and Time Tracking module using visual Mermaid diagrams.

---

## 1. Project Initiation & WBS Planning Flow

This flow illustrates the sequence of setting up a project, defining the Work Breakdown Structure, assigning task dependencies, and running circular dependency validation.

```mermaid
graph TD
    Start([Initiate Project Request]) --> Auth{Check permissions<br/>projects.project.write}
    Auth -- No --> Err403[Return 403 Forbidden]
    Auth -- Yes --> CreateProj[Create Project Model<br/>Set Name, Dates, Budget]
    CreateProj --> SetMembers[Assign Manager & Team Members<br/>Set custom hourly cost/bill rates]
    
    SetMembers --> CreateTask[Create WBS Task / Milestone]
    CreateTask --> CheckDates{Is Task Due Date<br/>within Project range?}
    CheckDates -- No --> Err422_Dates[Throw 422: Task due date<br/>exceeds project boundaries]
    CheckDates -- Yes --> AddDep{Assign Task Dependency?}
    
    AddDep -- Yes --> RunDFS[Execute Depth-First Search DFS<br/>on task dependency tree]
    RunDFS --> CycleDetect{Circular dependency<br/>detected?}
    CycleDetect -- Yes --> Err422_Cycle[Throw 422: Circular dependency<br/>loops are prohibited]
    CycleDetect -- No --> SaveDep[Link dependency_id & Save]
    AddDep -- No --> SaveTask[Save Task Model]
    
    SaveDep --> FinishPlanning([Planning Complete<br/>Project set to 'active'])
    SaveTask --> FinishPlanning
```

---

## 2. Kanban Board & WebSockets Real-Time Sync Flow

This flowchart describes the optimistic UI updates when dragging task cards and the tenant-scoped real-time broadcasting mechanics.

```mermaid
sequenceDiagram
    autonumber
    actor UserA as Assignee (Browser A)
    participant FE as Vue 3 Client (Pinia)
    participant WS as WebSocket Broker (Reverb/Redis)
    participant BE as Laravel API (Controller/Service)
    actor UserB as Manager (Browser B)

    UserA->>FE: Drags task card from 'Todo' to 'In Progress'
    Note over FE: Optimistic UI: Card moves instantly,<br/>displays spinner, locks controls.
    
    FE->>BE: PATCH /api/v1/tasks/{id}/status { status: "in_progress" }<br/>Headers: X-Tenant-Handle
    
    activate BE
    BE->>BE: Verify active tenant connection
    BE->>BE: Validate transition permission (TaskPolicy)
    BE->>BE: Update Task status in database
    
    BE-->>FE: Return 200 OK { task: TaskResource }
    deactivate BE
    
    Note over FE: Remove spinner, unlock card.
    
    BE->>WS: Dispatch TaskStatusUpdated Event
    activate WS
    Note over WS: Event maps to private channel:<br/>tenant-{handle}.project-{project_uuid}
    
    WS-->>UserB: Broadcast status update payload
    deactivate WS
    
    Note over UserB: Pinia store receives payload,<br/>moves card on User B's board in real-time.
```

---

## 3. Timesheet Logging, Validation, & Financial Integration Flow

This diagram traces the full lifecycle of an employee logging hours, the manager's approval board, and the automatic downstream integration with FMS billing and HRM payroll records.

```mermaid
graph TD
    StartLog([Log Time Request]) --> ValidateTime{Hours logged <= 16h<br/>for log date?}
    ValidateTime -- No --> Err422_Hours[Throw 422: Daily hours<br/>limit exceeded]
    
    ValidateTime -- Yes --> CheckLeave{Is Employee on approved<br/>leave on log_date?}
    CheckLeave -- Yes --> Err422_Leave[Throw 422: Cannot log hours<br/>during approved leave]
    
    CheckLeave -- No --> CheckLock{Is Payroll Period locked<br/>for target month?}
    CheckLock -- Yes --> Err422_Locked[Throw 422: Period locked;<br/>timesheet cannot be created]
    
    CheckLock -- No --> SaveTimesheet[Save Timesheet in 'draft' state]
    SaveTimesheet --> EmployeeSubmit[Employee Submits Timesheet]
    EmployeeSubmit --> StatusPending[Status flips to 'submitted']
    
    StatusPending --> ManagerReview{Manager Approves?}
    ManagerReview -- No --> RejectTimesheet[Status flips to 'rejected'<br/>Returns to employee for edits]
    
    ManagerReview -- Yes --> ApproveTimesheet[Status flips to 'approved'<br/>Timesheet locks]
    
    ApproveTimesheet --> FMS_Sync{Is Task Billable?}
    FMS_Sync -- Yes --> UnbilledAR[Register approved hours as unbilled inventory]
    UnbilledAR --> InvoicingJob[Client Invoice generated in FMS]
    InvoicingJob --> PostInvoiceGL[FMS Posts balanced journal:<br/>DR Accounts Receivable / CR Project Revenue]
    
    ApproveTimesheet --> HRM_Sync[Payroll Job runs at month-end]
    HRM_Sync --> CalcLaborCost[Calculate actual labor cost based on hourly rates]
    CalcLaborCost --> PostPayrollGL[Post payroll journal entry:<br/>DR Labor Expense / CR Salaries Payable]
```

---

## 4. Nightly Project Cost & Budget Reconciliation Flow

This flow maps the nightly automated calculation comparing planned project budgets against calculated labor costs and direct financial expenses.

```mermaid
graph TD
    CronTrigger([Nightly Cron Trigger]) --> GetActiveProjects[Fetch all projects with status = 'active']
    
    subgraph Calculation Loop [For Each Project]
        FetchTimesheets[Query all approved timesheets for project]
        ResolveRates[Resolve hourly rate for each log entry:<br/>Pivot assignee rate -> Project rate -> HRM salary conversion]
        SumLabor[Sum total labor cost: Hours x Resolved Rate]
        
        FetchExpenses[Query FMS for direct expenses posted with project_id]
        SumTotalCost[Sum Actual Cost = Labor Cost + Direct Expenses]
        
        CompareBudget{Actual Cost ><br/>Project Budget?}
        CompareBudget -- Exceeds --> MarkOver[Flag Project as 'over_budget']
        MarkOver --> DispatchAlerts[Dispatch real-time WebSocket alerts to Project Manager]
        MarkOver --> WriteAudit[Log cost-variance breach to Audit Trail]
        
        CompareBudget -- Within --> UpdateVariance[Calculate variance & percentage used]
    end
    
    CalculationLoop --> Complete([Nightly Cost Sync Complete])
```
