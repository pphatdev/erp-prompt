<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\WorkSchedule;
use App\Tenants\Modules\HRM\Services\LeaveService;
use App\Tenants\Modules\HRM\Services\WorkScheduleService;
use Carbon\CarbonImmutable;
use Tests\Feature\TenantTestCase;

/**
 * Phase 10 - WorkScheduleService resolver + LeaveService integration.
 *
 * Covers:
 *   - Default global seed (Mon-Fri full, Sat half, Sun off)
 *   - Department overrides (e.g. Sun-Thu branch)
 *   - Employee overrides (e.g. part-time engineer)
 *   - Hierarchy precedence (Employee > Department > Global)
 *   - LeaveService::countWorkingDays honors the resolver
 *   - upsertWeek atomically replaces a week for a target
 *   - clearTarget removes overrides so the target falls back
 */
class WorkScheduleTest extends TenantTestCase
{
    private WorkScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkScheduleService::class);
    }

    // ---------- Default global seed -----------------------------------

    public function test_default_global_schedule_is_seeded_with_mon_fri_full_and_sat_half(): void
    {
        $rows = $this->service->listFor(WorkSchedule::TARGET_GLOBAL, null)
            ->keyBy('day_of_week');

        $this->assertCount(7, $rows, 'Default seed must produce all 7 days.');

        // Mon-Fri: full day, 480 minutes (8h)
        for ($dow = 1; $dow <= 5; $dow++) {
            $this->assertTrue((bool) $rows[$dow]->is_work_day, "Day {$dow} must be a work day.");
            $this->assertSame(480, $rows[$dow]->totalMinutes(), "Day {$dow} must clock 8h.");
        }
        // Saturday: half day, 240 minutes
        $this->assertTrue((bool) $rows[6]->is_work_day);
        $this->assertSame(240, $rows[6]->totalMinutes());
        // Sunday: off
        $this->assertFalse((bool) $rows[7]->is_work_day);
        $this->assertSame(0, $rows[7]->totalMinutes());
    }

    // ---------- Resolver hierarchy ------------------------------------

    public function test_resolver_returns_global_when_no_overrides(): void
    {
        $monday = CarbonImmutable::create(2026, 7, 6); // Mon
        $resolved = $this->service->resolveFor($monday, null);

        $this->assertTrue($resolved['is_work_day']);
        $this->assertSame('global', $resolved['source']);
    }

    public function test_department_override_beats_global(): void
    {
        $dept = Department::create(['name' => 'Branch Cambodia']);
        // Branch follows Sun-Thu; Friday is off.
        $this->service->upsertWeek(WorkSchedule::TARGET_DEPARTMENT, $dept->id, [
            ['dayOfWeek' => 5, 'isWorkDay' => false, 'intervals' => []], // Fri off
            ['dayOfWeek' => 7, 'isWorkDay' => true,  'intervals' => [['start' => '08:00', 'end' => '17:00']]], // Sun on
        ]);

        $employee = Employee::create([
            'first_name' => 'Branch', 'last_name' => 'Staff',
            'email' => 'branch.staff@ws.example',
            'employee_id' => 'WS-001', 'status' => 'active',
            'department_id' => $dept->id,
        ]);

        // Friday — overridden to off
        $friday = CarbonImmutable::create(2026, 7, 10);
        $resolvedFri = $this->service->resolveFor($friday, $employee);
        $this->assertFalse($resolvedFri['is_work_day']);
        $this->assertSame('department', $resolvedFri['source']);

        // Sunday — overridden to on
        $sunday = CarbonImmutable::create(2026, 7, 12);
        $resolvedSun = $this->service->resolveFor($sunday, $employee);
        $this->assertTrue($resolvedSun['is_work_day']);
        $this->assertSame('department', $resolvedSun['source']);

        // Monday — falls through to global (no department override on Mon)
        $monday = CarbonImmutable::create(2026, 7, 6);
        $resolvedMon = $this->service->resolveFor($monday, $employee);
        $this->assertSame('global', $resolvedMon['source']);
    }

    public function test_employee_override_beats_department_and_global(): void
    {
        $dept = Department::create(['name' => 'Eng']);
        $this->service->upsertWeek(WorkSchedule::TARGET_DEPARTMENT, $dept->id, [
            ['dayOfWeek' => 5, 'isWorkDay' => false, 'intervals' => []], // dept: Fri off
        ]);

        $employee = Employee::create([
            'first_name' => 'Part', 'last_name' => 'Timer',
            'email' => 'part.timer@ws.example',
            'employee_id' => 'WS-002', 'status' => 'active',
            'department_id' => $dept->id,
        ]);
        // Employee personally works only Mon + Wed + Fri
        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '13:00']]],
            ['dayOfWeek' => 2, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 3, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '13:00']]],
            ['dayOfWeek' => 4, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 5, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '13:00']]], // beats dept's "Fri off"
        ]);

        $monday  = CarbonImmutable::create(2026, 7, 6);
        $tuesday = CarbonImmutable::create(2026, 7, 7);
        $friday  = CarbonImmutable::create(2026, 7, 10);

        $this->assertSame('employee', $this->service->resolveFor($monday,  $employee)['source']);
        $this->assertSame('employee', $this->service->resolveFor($tuesday, $employee)['source']);
        $this->assertSame('employee', $this->service->resolveFor($friday,  $employee)['source']);

        $this->assertTrue($this->service->isWorkDay($monday, $employee));
        $this->assertFalse($this->service->isWorkDay($tuesday, $employee));
        $this->assertTrue($this->service->isWorkDay($friday, $employee));
    }

    // ---------- countWorkingDays --------------------------------------

    public function test_count_working_days_uses_global_default_when_no_overrides(): void
    {
        // 2026-07-06 (Mon) to 2026-07-12 (Sun) = Mon..Sat (work) = 6 work days
        $start = CarbonImmutable::create(2026, 7, 6);
        $end   = CarbonImmutable::create(2026, 7, 12);

        $this->assertSame(6, $this->service->countWorkingDays($start, $end, null));
    }

    public function test_count_working_days_honors_employee_override(): void
    {
        $employee = Employee::create([
            'first_name' => 'Half', 'last_name' => 'Schedule',
            'email' => 'half@ws.example',
            'employee_id' => 'WS-003', 'status' => 'active',
        ]);
        // Mon + Wed + Fri only
        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '17:00']]],
            ['dayOfWeek' => 2, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 3, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '17:00']]],
            ['dayOfWeek' => 4, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 5, 'isWorkDay' => true,  'intervals' => [['start' => '09:00', 'end' => '17:00']]],
            ['dayOfWeek' => 6, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 7, 'isWorkDay' => false, 'intervals' => []],
        ]);

        // Mon-Sun = 3 work days for this employee.
        $start = CarbonImmutable::create(2026, 7, 6);
        $end   = CarbonImmutable::create(2026, 7, 12);
        $this->assertSame(3, $this->service->countWorkingDays($start, $end, $employee));
    }

    public function test_leave_service_delegates_to_resolver(): void
    {
        $employee = Employee::create([
            'first_name' => 'Leave', 'last_name' => 'Resolver',
            'email' => 'leave.resolver@ws.example',
            'employee_id' => 'WS-004', 'status' => 'active',
        ]);
        // Override: only Mon-Thu, Fri-Sun off
        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => true,  'intervals' => [['start' => '08:00', 'end' => '17:00']]],
            ['dayOfWeek' => 2, 'isWorkDay' => true,  'intervals' => [['start' => '08:00', 'end' => '17:00']]],
            ['dayOfWeek' => 3, 'isWorkDay' => true,  'intervals' => [['start' => '08:00', 'end' => '17:00']]],
            ['dayOfWeek' => 4, 'isWorkDay' => true,  'intervals' => [['start' => '08:00', 'end' => '17:00']]],
            ['dayOfWeek' => 5, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 6, 'isWorkDay' => false, 'intervals' => []],
            ['dayOfWeek' => 7, 'isWorkDay' => false, 'intervals' => []],
        ]);

        $start = CarbonImmutable::create(2026, 7, 6); // Mon
        $end   = CarbonImmutable::create(2026, 7, 12); // Sun
        // LeaveService bridges the resolver via the new employeeId arg.
        $leave = app(LeaveService::class);
        $this->assertSame(4, $leave->countWorkingDays($start, $end, $employee->id));
        // Without an employee, falls back to global (Mon..Sat = 6 work days).
        $this->assertSame(6, $leave->countWorkingDays($start, $end));
    }

    // ---------- upsertWeek + clearTarget ------------------------------

    public function test_upsert_week_replaces_existing_rows_atomically(): void
    {
        $employee = Employee::create([
            'first_name' => 'Up', 'last_name' => 'Sert',
            'email' => 'up.sert@ws.example',
            'employee_id' => 'WS-005', 'status' => 'active',
        ]);

        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => true, 'intervals' => [['start' => '08:00', 'end' => '12:00']]],
        ]);
        // Update Monday to 09:00-13:00.
        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => true, 'intervals' => [['start' => '09:00', 'end' => '13:00']]],
        ]);

        $rows = $this->service->listFor(WorkSchedule::TARGET_EMPLOYEE, $employee->id);
        $this->assertSame(1, $rows->count(), 'No duplicate rows after re-upsert.');
        $this->assertSame([['start' => '09:00', 'end' => '13:00']], $rows->first()->intervals);
    }

    public function test_clear_target_removes_overrides_and_falls_back_to_parent(): void
    {
        $employee = Employee::create([
            'first_name' => 'Clear', 'last_name' => 'Target',
            'email' => 'clear.target@ws.example',
            'employee_id' => 'WS-006', 'status' => 'active',
        ]);
        $this->service->upsertWeek(WorkSchedule::TARGET_EMPLOYEE, $employee->id, [
            ['dayOfWeek' => 1, 'isWorkDay' => false, 'intervals' => []], // override: Monday off
        ]);

        $monday = CarbonImmutable::create(2026, 7, 6);
        $this->assertSame('employee', $this->service->resolveFor($monday, $employee)['source']);

        $deleted = $this->service->clearTarget(WorkSchedule::TARGET_EMPLOYEE, $employee->id);
        $this->assertSame(1, $deleted);

        // Now falls back to global (Mon is a work day).
        $this->assertSame('global', $this->service->resolveFor($monday, $employee)['source']);
        $this->assertTrue($this->service->isWorkDay($monday, $employee));
    }

    public function test_clear_target_rejects_global(): void
    {
        $this->expectException(\DomainException::class);
        $this->service->clearTarget(WorkSchedule::TARGET_GLOBAL, null);
    }
}
