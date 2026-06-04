<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Projects;

use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\Timesheet;
use App\Tenants\Modules\Projects\Services\TaskService;
use DomainException;
use Tests\Feature\TenantTestCase;

/**
 * Projects Phase 1 (timesheet validation) - covers the three guards added
 * to TaskService::logTime: daily-hour cap, approved-leave block,
 * closed-payroll-period lock.
 */
class TimesheetGuardsTest extends TenantTestCase
{
    private TaskService $service;
    private Employee $employee;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TaskService::class);

        $this->employee = Employee::create([
            'first_name' => 'Tim', 'last_name' => 'Sheets',
            'email' => 'tim.sheets@projects.example',
            'employee_id' => 'TS-001', 'status' => 'active',
        ]);
        $project = Project::create([
            'name' => 'Guard Fixture', 'status' => 'active',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $this->task = Task::create([
            'project_id' => $project->id,
            'title' => 'Initial task',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
    }

    public function test_happy_path_logs_time_and_flips_todo_to_in_progress(): void
    {
        $ts = $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-06', // Mon
            'hours_worked' => 4.0,
        ]);

        $this->assertSame(4.0, (float) $ts->hours_worked);
        $this->assertSame('in_progress', $this->task->fresh()->status,
            'First time-log on a todo task must flip the task to in_progress.');
    }

    public function test_zero_or_negative_hours_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/greater than zero/');
        $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-07',
            'hours_worked' => 0,
        ]);
    }

    public function test_daily_hour_cap_rejects_when_sum_exceeds_16(): void
    {
        // Pre-seed 10h on the date.
        Timesheet::create([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-08',
            'hours_worked' => 10.0,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Daily hour cap exceeded/');
        $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-08',
            'hours_worked' => 7.0, // 10 + 7 = 17 > 16 cap
        ]);
    }

    public function test_daily_hour_cap_allows_exactly_16(): void
    {
        Timesheet::create([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-09',
            'hours_worked' => 8.0,
        ]);

        // 8 + 8 = 16 exactly — must not throw.
        $ts = $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-09',
            'hours_worked' => 8.0,
        ]);

        $this->assertSame(8.0, (float) $ts->hours_worked);
    }

    public function test_approved_leave_blocks_logging_on_that_date(): void
    {
        $leaveType = LeaveType::create(['name' => 'Annual', 'annual_allowance' => 20]);
        Leave::create([
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date'    => '2026-04-13', // Mon
            'end_date'      => '2026-04-15', // Wed - 3-day leave
            'days'          => 3,
            'status'        => 'approved',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/approved leave/');
        $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-14', // inside the leave window
            'hours_worked' => 4.0,
        ]);
    }

    public function test_pending_leave_does_not_block_logging(): void
    {
        $leaveType = LeaveType::create(['name' => 'Annual2', 'annual_allowance' => 20]);
        Leave::create([
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date'    => '2026-04-20',
            'end_date'      => '2026-04-20',
            'days'          => 1,
            'status'        => 'pending', // not approved
        ]);

        $ts = $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-20',
            'hours_worked' => 6.0,
        ]);
        $this->assertSame(6.0, (float) $ts->hours_worked);
    }

    public function test_closed_payroll_period_blocks_logging(): void
    {
        PayrollPeriod::create([
            'name'       => '2026-04 sealed',
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-30',
            'status'     => 'closed',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/closed payroll period/');
        $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-04-22',
            'hours_worked' => 2.0,
        ]);
    }

    public function test_open_payroll_period_does_not_block_logging(): void
    {
        PayrollPeriod::create([
            'name'       => '2026-05 open',
            'start_date' => '2026-05-01',
            'end_date'   => '2026-05-31',
            'status'     => 'draft', // not closed
        ]);

        $ts = $this->service->logTime([
            'task_id'      => $this->task->id,
            'employee_id'  => $this->employee->id,
            'log_date'     => '2026-05-15',
            'hours_worked' => 3.0,
        ]);
        $this->assertSame(3.0, (float) $ts->hours_worked);
    }
}
