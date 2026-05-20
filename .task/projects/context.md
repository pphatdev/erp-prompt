# Feature Context: Project Management (Backend)

Implementation phases for the Projects module, focusing on project tracking, tasks, and resource allocation.

## Implementation Phases (Backend Only)

### Phase 1: Core Schema
- [ ] Create migrations for `projects`, `tasks`, and `timesheets`.
- [ ] Implement models with `BelongsToTenant` and `Auditable`.

### Phase 2: Project & Task Logic
- [ ] Implement `ProjectService` for project lifecycle management.
- [ ] Implement `TaskService` for Kanban/Gantt updates and dependencies.
- [ ] Implement logic for budget vs. actual cost calculation.

### Phase 3: Time Tracking
- [ ] Create functionality to log hours against specific tasks via `timesheets`.
- [ ] (Future) Integrate with HRM payroll engine.

### Phase 4: API & Access Control
- [ ] Create `ProjectController`, `TaskController`, and `TimesheetController`.
- [ ] Implement `ProjectResource`, `TaskResource`, and `TimesheetResource`.
- [ ] Define `projects.planning.*`, `projects.tasks.*`, and `projects.resources.*` permission policies.

### Phase 5: QA & Integration Testing
- [ ] P0 Tenancy Isolation tests.
- [ ] P1 Logic tests for budget calculations and task status transitions.
