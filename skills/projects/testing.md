# Testing Strategy: Project Management & Time Tracking

This document outlines the testing priority matrix, backend Pest test implementations, frontend E2E and visual assertions, and API integration scenarios for the Project Management module.

---

## 1. Priority Matrix (P0 - P2)

Testing must cover security, calculation accuracy, state machines, and real-time operations, prioritizing tenancy isolation above all else.

| Priority | Category | Requirement / Test Case | Focus Area |
|---|---|---|---|
| **P0** | **Tenancy Isolation** | Project A data (projects, tasks, timesheets, comments) is strictly invisible to Tenant B. Querying across tenants must throw `404 Not Found`. | DB Connection Isolation |
| **P0** | **Authorization (IAM)** | Only added project members or managers can read/write project assets. Timesheet `.self` edits are locked to the user's `employee_id`. | Policies & RBAC |
| **P1** | **Scheduling Engine** | Adding cyclic dependencies (DFS) throws `422`. Shifting a parent task's `due_date` correctly and recursively cascades to all dependent tasks. | Algorithms & Transactions |
| **P1** | **Time Log Validations** | Daily hour cap check (max 16 hours), approved leave check (no logs on vacation days), and monthly payroll lock verification. | Services & Business Rules |
| **P1** | **WebSockets Broadcast** | Dragging a Kanban card fires a tenant-scoped, project-scoped event (`TaskStatusUpdated`) to the correct channel. | Real-time Operations |
| **P2** | **Budget Calculations** | Hourly cost rate resolves correctly from pivot -> project member rate -> converted employee salary rate. Variance recalculations flag correctly. | cost accounting |
| **P2** | **FMS Posting Hooks** | Approved timesheets register as unbilled revenue; invoicing correctly transitions logs to `invoiced` and posts the double-entry journal. | Multi-Module Integrations |

---

## 2. Backend Testing (Pest PHP)

Tests run exclusively on the `erp_system_test` database connection (enforced by `phpunit.xml`). Seeders must run to establish workflow states.

### A. Data Isolation Test (P0)
This test asserts that a request under Tenant A's context attempting to read or modify Tenant B's project resource is met with a clean `404 Not Found`.

```php
<?php

use App\Models\Tenant\Project;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Laravel\Passport\Passport;

uses(Tests\TestCase::class)->in(__DIR__);

test('tenant A cannot access tenant B project resources', function () {
    // 1. Establish Tenant A & Tenant B
    $tenantA = createTenant('tenant-a');
    $tenantB = createTenant('tenant-b');

    // 2. Create Project in Tenant B
    tenancy()->initialize($tenantB);
    $projectB = Project::create([
        'name' => 'Secret Tenant B Operations',
        'budget' => 50000.00,
        'status' => 'active',
        'tenant_id' => 'tenant-b',
    ]);
    tenancy()->end();

    // 3. Authenticate User under Tenant A
    tenancy()->initialize($tenantA);
    $userA = User::factory()->create();
    $employeeA = Employee::factory()->create(['user_id' => $userA->id]);
    $userA->assignRole('employee'); // Has projects.project.read.self
    
    Passport::actingAs($userA);

    // 4. Request Tenant B's Project via Tenant A's API connection
    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'tenant-a',
    ])->getJson("/api/v1/projects/{$projectB->id}");

    // 5. Assert isolation hides the project and returns a 404
    $response->assertStatus(404);
});
```

### B. Cyclic Dependency Prevention Test (P1)
This test verifies that scheduling a dependency that would form a loop (e.g., A -> B -> A) is intercepted by the cycle detection logic.

```php
test('prevent cyclic dependencies in WBS task planning', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);
    
    $project = Project::create([
        'name' => 'Dependency Test Project',
        'tenant_id' => 'demo-tenant',
    ]);

    $taskA = Task::create([
        'project_id' => $project->id,
        'title' => 'Task A',
        'tenant_id' => 'demo-tenant',
    ]);

    $taskB = Task::create([
        'project_id' => $project->id,
        'title' => 'Task B',
        'dependency_id' => $taskA->id, // B depends on A
        'tenant_id' => 'demo-tenant',
    ]);

    // Attempt to make A depend on B (Creates A -> B -> A loop)
    $user = User::factory()->create();
    $user->givePermissionTo('projects.task.write');
    Passport::actingAs($user);

    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->patchJson("/api/v1/tasks/{$taskA->id}", [
        'dependency_id' => $taskB->id,
    ]);

    // Assert validation error
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['dependency_id']);
    expect($response->json('errors.dependency_id.0'))->toContain('circular');
});
```

### C. Leave Overlap Time Logging Guard Test (P1)
This test asserts that timesheet logs are rejected when an employee attempts to log hours during an approved leave block.

```php
test('cannot log timesheet hours on approved leave date', function () {
    $tenant = createTenant('demo-tenant');
    tenancy()->initialize($tenant);

    $employee = Employee::factory()->create();
    
    // Create approved leave on target date
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id,
        'start_date' => '2026-06-05',
        'end_date' => '2026-06-05',
        'status' => 'approved',
        'tenant_id' => 'demo-tenant',
    ]);

    $project = Project::create(['name' => 'Operations', 'tenant_id' => 'demo-tenant']);
    $task = Task::create(['project_id' => $project->id, 'title' => 'Code Audit', 'tenant_id' => 'demo-tenant']);

    $user = User::factory()->create(['employee_id' => $employee->id]);
    Passport::actingAs($user);

    // Attempt to log time
    $response = $this->withHeaders([
        'X-Tenant-Handle' => 'demo-tenant',
    ])->postJson("/api/v1/timesheets", [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'log_date' => '2026-06-05',
        'hours_worked' => 8.00,
    ]);

    // Assert transition rejected due to approved leave overlap
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('leave');
});
```

---

## 3. Frontend E2E & Component Verification

Tests are written in Playwright (E2E) and Vitest (Component) under the `frontend/` workspace.

### E2E Journeys
1. **Gantt Drag-and-Reschedule**:
   - Drag Task A's end handle in the Gantt component.
   - Assert that Task B's start coordinate slides correspondingly to preserve the dependency gap.
   - Verify that a network request `PATCH /api/v1/tasks/{id}` is sent containing updated dates.
2. **Kanban Card Drag**:
   - Drag card from the 'Todo' column to the 'In Progress' column.
   - Assert card has `card-spinner` or opacity drop.
   - Intercept API call, return successful 200 OK.
   - Assert card spinner is removed and card snaps solid inside 'In Progress'.

---

## 4. Postman Integration Scenarios

All integration test routes are verified inside `docs/postman/erp_collection.json`. The environment requires these pre-populated variables:
- `{{tenant_handle}}` = `demo`
- `{{access_token}}` = Bearer OAuth JWT
- `{{project_id}}` = Active Project UUID

### Automated Postman Tests:
- **Initiate Project & WBS**:
  - Request: `POST /api/v1/projects` with budget details.
  - Script asserts response is 201 and stores `{{project_id}}`.
- **Log Billable Hours**:
  - Request: `POST /api/v1/timesheets` with hours and billable flags.
  - Script asserts response structure and locks hours.
- **Budget Summary Check**:
  - Request: `GET /api/v1/projects/{{project_id}}/budget-status`.
  - Script asserts `actual_cost` is calculated and `percentage_used` matches the logged values.
