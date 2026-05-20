<?php

namespace Tests\Feature;

use App\Models\Tenant\Employee;

class ProjectManagementTest extends TenantTestCase
{
    /**
     * Test Projects Module endpoints listing accessibility.
     */
    public function test_projects_module_endpoints()
    {
        $routes = ['/api/v1/projects', '/api/v1/tasks', '/api/v1/timesheets'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test Projects module features including Projects, Tasks, and Timesheet logging.
     */
    public function test_project_management_features_workflow()
    {
        // 1. Create a Project
        $projPayload = [
            'name' => 'ERP Development Phase 2',
            'description' => 'Implementation of HR and Project modules.',
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'budget' => 75000.00,
        ];

        $projResponse = $this->tenantRequest('POST', '/api/v1/projects', $projPayload);
        $projResponse->assertStatus(201);
        $projectId = $projResponse->json('data.id');
        $this->assertNotNull($projectId);

        // 2. Create a Task under the Project
        $taskPayload = [
            'project_id' => $projectId,
            'title' => 'Write Feature Tests',
            'description' => 'Develop automated tests for all tenant modules.',
            'due_date' => '2026-06-15',
            'priority' => 'high',
        ];

        $taskResponse = $this->tenantRequest('POST', '/api/v1/tasks', $taskPayload);
        $taskResponse->assertStatus(201);
        $taskId = $taskResponse->json('data.id');
        $this->assertNotNull($taskId);

        // 3. Update Task Status
        $statusResponse = $this->tenantRequest('PATCH', "/api/v1/tasks/{$taskId}/status", [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertStatus(200)->assertJsonPath('data.status', 'in_progress');

        // 4. Create an Employee directly using Eloquent to record timesheet logs
        $employee = Employee::create([
            'employee_id' => 'EMP-007',
            'first_name' => 'James',
            'last_name' => 'Bond',
            'email' => 'james.bond@mi6.gov',
        ]);

        // 5. Log a Timesheet entry
        $timesheetPayload = [
            'task_id' => $taskId,
            'employee_id' => $employee->id,
            'log_date' => '2026-05-19',
            'hours_worked' => 8.50,
            'notes' => 'Completed full suite coverage for all 12 modules.',
        ];

        $timesheetResponse = $this->tenantRequest('POST', '/api/v1/timesheets', $timesheetPayload);
        $timesheetResponse->assertStatus(201);
        
        $this->assertDatabaseHas('timesheets', [
            'task_id' => $taskId,
            'employee_id' => $employee->id,
            'hours_worked' => 8.50,
        ]);
    }
}
