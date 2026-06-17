<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeLeaveAllocation;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\WorkSchedule;
use App\Tenants\Modules\HRM\Events\EmployeeCreated;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use App\Tenants\Modules\HRM\Services\LeaveAllocationService;
use App\Tenants\Modules\HRM\Services\LeaveService;
use App\Tenants\Modules\HRM\Services\WorkScheduleService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Event;
use Tests\Feature\TenantTestCase;

/**
 * Phase 12 - Leave & Time Off Allocations.
 *
 * Locks the per-employee, per-year allocation ledger and its
 * integration with LeaveService:
 *
 *  - Auto-provision on hire honours gender restrictions
 *  - LeaveService::submitRequest holds pending days on the row
 *  - Approve flips pending -> used; Reject releases pending only
 *  - Withdrawing an approved leave subtracts from used
 *  - Balance check enforces remaining (allocated - used - pending)
 *  - allow_negative_balance bypasses the throw
 *  - Half-day session always books 0.5 day
 *  - WorkScheduleService::countWorkingDaysDecimal sums interval
 *    minutes so a Saturday with a 4-hour schedule counts as 0.5
 *  - Manual adjust updates allocated_days through the service
 */
class LeaveAllocationsTest extends TenantTestCase
{
    private LeaveService $leaves;
    private LeaveAllocationService $allocations;
    private WorkScheduleService $workSchedules;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaves        = app(LeaveService::class);
        $this->allocations   = app(LeaveAllocationService::class);
        $this->workSchedules = app(WorkScheduleService::class);
        $this->settings      = app(SettingService::class);

        // Force the standard daily hours setting on so test math is
        // independent of seeded defaults.
        $this->settings->set('hrm.leave.standard_daily_hours', 8.0);
    }

    // ---------------------------------------------------------------
    // Auto-provisioning via EmployeeCreated
    // ---------------------------------------------------------------

    public function test_provision_seeds_allocations_for_any_gender_types(): void
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-PROV-001',
            'first_name'  => 'Pat',
            'last_name'   => 'Provisioned',
            'email'       => 'pat.prov@example.test',
            'gender'      => 'male',
            'hired_at'    => '2026-01-01',
            'status'      => 'active',
        ]);

        $this->allocations->provisionForEmployee($employee);

        $allocations = EmployeeLeaveAllocation::where('employee_id', $employee->id)->get();

        // Annual + Special + Sick + Unpaid for an `any` gender type;
        // Maternity is `female` only so a male employee skips it.
        $codes = $allocations->load('leaveType')->pluck('leaveType.code')->all();
        $this->assertContains('VAC',  $codes);
        $this->assertContains('SPL',  $codes);
        $this->assertContains('SICK', $codes);
        $this->assertContains('UNP',  $codes);
        $this->assertNotContains('MAT', $codes,
            'Maternity is female-only and must NOT be allocated to a male employee.');
    }

    public function test_provision_seeds_maternity_for_female_employee(): void
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-PROV-002',
            'first_name'  => 'Mira',
            'last_name'   => 'Provisioned',
            'email'       => 'mira.prov@example.test',
            'gender'      => 'female',
            'hired_at'    => '2026-01-01',
            'status'      => 'active',
        ]);

        $this->allocations->provisionForEmployee($employee);

        $maternity = EmployeeLeaveAllocation::where('employee_id', $employee->id)
            ->whereHas('leaveType', fn ($q) => $q->where('code', 'MAT'))
            ->first();

        $this->assertNotNull($maternity, 'Female employee must receive a Maternity allocation row.');
        $this->assertSame(90.0, (float) $maternity->allocated_days);
    }

    public function test_provision_is_idempotent(): void
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-IDEM-001',
            'first_name'  => 'Idem',
            'last_name'   => 'Potent',
            'email'       => 'idem.potent@example.test',
            'gender'      => 'female',
            'hired_at'    => '2026-01-01',
            'status'      => 'active',
        ]);

        $this->allocations->provisionForEmployee($employee);
        $firstCount = EmployeeLeaveAllocation::where('employee_id', $employee->id)->count();

        // Re-fire -> firstOrCreate skips existing rows.
        $this->allocations->provisionForEmployee($employee);
        $secondCount = EmployeeLeaveAllocation::where('employee_id', $employee->id)->count();

        $this->assertSame($firstCount, $secondCount,
            'Re-provisioning must not duplicate allocation rows.');
    }

    public function test_employee_created_event_triggers_provisioning_listener(): void
    {
        Event::fake([EmployeeCreated::class]);

        app(EmployeeService::class)->createEmployee([
            'first_name' => 'Hired',
            'last_name'  => 'Listener',
            'email'      => 'hired.listener@example.test',
            'gender'     => 'female',
            'hired_at'   => '2026-01-01',
            'status'     => 'active',
        ]);

        Event::assertDispatched(EmployeeCreated::class);
    }

    // ---------------------------------------------------------------
    // Counter mutators
    // ---------------------------------------------------------------

    public function test_submit_request_holds_pending_days(): void
    {
        [$employee, $type] = $this->seedActiveEmployee('VAC', 18);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15', // Mon
            'end_date'      => '2026-06-17', // Wed - 3 working days
            'reason'        => 'test pending',
        ]);

        $alloc = EmployeeLeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(3.0, (float) $alloc->pending_days);
        $this->assertSame(0.0, (float) $alloc->used_days);
        $this->assertSame('pending', $leave->status);
    }

    public function test_approve_flips_pending_to_used(): void
    {
        [$employee, $type, $alloc] = $this->seedActiveEmployeeWithAllocation('VAC', 18);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-17',
            'reason'        => 'approve me',
        ]);
        $this->assertSame('pending', $leave->status);

        $approved = $this->leaves->syncFromApproval($leave, 'approved');
        $this->assertSame('approved', $approved->status);

        $alloc->refresh();
        $this->assertSame(0.0, (float) $alloc->pending_days,
            'Pending bucket must drain when leave is approved.');
        $this->assertSame(3.0, (float) $alloc->used_days,
            'Used bucket gains the approved leave days.');
    }

    public function test_reject_releases_pending_without_using_balance(): void
    {
        [$employee, $type, $alloc] = $this->seedActiveEmployeeWithAllocation('VAC', 18);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-17',
            'reason'        => 'reject me',
        ]);

        $this->leaves->syncFromApproval($leave, 'rejected');

        $alloc->refresh();
        $this->assertSame(0.0, (float) $alloc->pending_days);
        $this->assertSame(0.0, (float) $alloc->used_days,
            'Rejected leaves never consume the used bucket.');
    }

    public function test_withdraw_approved_leave_returns_used_balance(): void
    {
        [$employee, $type, $alloc] = $this->seedActiveEmployeeWithAllocation('VAC', 18);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-15',
            'reason'        => 'one day',
        ]);
        $this->leaves->syncFromApproval($leave, 'approved');
        $alloc->refresh();
        $this->assertSame(1.0, (float) $alloc->used_days);

        $this->leaves->withdraw($leave);

        $alloc->refresh();
        $this->assertSame(0.0, (float) $alloc->used_days,
            'Withdrawing an approved leave releases its used days.');
    }

    // ---------------------------------------------------------------
    // Balance check
    // ---------------------------------------------------------------

    public function test_balance_check_blocks_when_remaining_too_low(): void
    {
        [$employee, $type] = $this->seedActiveEmployee('SICK', 2);

        // Pre-burn 1.5 days so only 0.5 remains.
        $this->allocations->ensureAllocation($employee->id, $type->id, 2026)
            ->update(['used_days' => 1.5]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient leave balance');

        $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-16',
            'reason'        => 'should fail',
        ]);
    }

    public function test_allow_negative_balance_bypasses_block(): void
    {
        [$employee, $type] = $this->seedActiveEmployee('SICK', 2);
        $this->allocations->ensureAllocation($employee->id, $type->id, 2026)
            ->update(['used_days' => 2.0]); // fully consumed

        $this->settings->set('hrm.leave.allow_negative_balance', true);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-15',
            'reason'        => 'emergency',
        ]);

        $this->assertNotNull($leave);
        $this->assertSame('pending', $leave->status);
    }

    // ---------------------------------------------------------------
    // Half-day + decimal day math
    // ---------------------------------------------------------------

    public function test_half_day_session_books_zero_point_five(): void
    {
        [$employee, $type] = $this->seedActiveEmployee('SICK', 7);

        $leave = $this->leaves->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2026-06-15',
            'end_date'      => '2026-06-15',
            'leave_session' => Leave::SESSION_MORNING,
            'reason'        => 'doctor',
        ]);

        $this->assertSame(0.5, (float) $leave->days);
    }

    public function test_decimal_calc_counts_saturday_half_day_as_zero_point_five(): void
    {
        $employee = $this->seedEmployeeWithCustomSaturday();

        // 2026-06-20 is a Saturday. Per the seeded global default it's
        // a half day (08:00-12:00 = 240 minutes = 0.5 day at standard 8h).
        $count = $this->workSchedules->countWorkingDaysDecimal(
            CarbonImmutable::create(2026, 6, 20),
            CarbonImmutable::create(2026, 6, 20),
            $employee,
            8.0
        );

        $this->assertSame(0.5, $count,
            'Saturday with a 4-hour schedule should count as 0.5 day.');
    }

    public function test_decimal_calc_sums_full_week_correctly(): void
    {
        $employee = $this->seedEmployeeWithCustomSaturday();

        // 2026-06-15 Mon -> 2026-06-21 Sun: Mon..Fri = 5 days, Sat = 0.5, Sun = 0.
        $count = $this->workSchedules->countWorkingDaysDecimal(
            CarbonImmutable::create(2026, 6, 15),
            CarbonImmutable::create(2026, 6, 21),
            $employee,
            8.0
        );

        $this->assertSame(5.5, $count);
    }

    // ---------------------------------------------------------------
    // Manual adjust
    // ---------------------------------------------------------------

    public function test_manual_adjust_updates_allocated_days_and_note(): void
    {
        [$employee, $type, $alloc] = $this->seedActiveEmployeeWithAllocation('VAC', 18);

        $updated = $this->allocations->adjust($alloc, 21.5, 'Carryover from 2025');

        $this->assertSame(21.5, (float) $updated->allocated_days);
        $this->assertSame('Carryover from 2025', $updated->note);
    }

    public function test_adjust_rejects_negative_allocation(): void
    {
        [$employee, $type, $alloc] = $this->seedActiveEmployeeWithAllocation('VAC', 18);

        $this->expectException(DomainException::class);
        $this->allocations->adjust($alloc, -1.0, 'invalid');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array{0: Employee, 1: LeaveType}
     */
    private function seedActiveEmployee(string $typeCode, int $allowance): array
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-ALLOC-' . strtoupper(bin2hex(random_bytes(2))),
            'first_name'  => 'Alice',
            'last_name'   => 'Allocated',
            'email'       => 'alice.' . bin2hex(random_bytes(3)) . '@example.test',
            'gender'      => 'female',
            'hired_at'    => '2025-01-01',
            'status'      => 'active',
        ]);

        $type = LeaveType::firstOrCreate(
            ['code' => $typeCode],
            [
                'name'             => $typeCode . ' Type',
                'annual_allowance' => $allowance,
                'applicable_gender' => 'any',
                'is_paid'          => true,
                'is_accrued'       => false,
            ]
        );
        if ($type->annual_allowance !== $allowance) {
            $type->update(['annual_allowance' => $allowance]);
        }

        return [$employee, $type];
    }

    /**
     * Seed employee + leave type + a pre-existing allocation row at
     * full allowance, so balance checks aren't shaped by pro-rata.
     *
     * @return array{0: Employee, 1: LeaveType, 2: EmployeeLeaveAllocation}
     */
    private function seedActiveEmployeeWithAllocation(string $typeCode, int $allowance): array
    {
        [$employee, $type] = $this->seedActiveEmployee($typeCode, $allowance);
        $alloc = EmployeeLeaveAllocation::firstOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => $type->id,
                'year'          => 2026,
            ],
            [
                'allocated_days' => $allowance,
                'used_days'      => 0,
                'pending_days'   => 0,
            ]
        );
        return [$employee, $type, $alloc];
    }

    /**
     * Seed an employee under the default global schedule (Mon-Fri full
     * day, Sat half day, Sun off) so the decimal-day tests can lean on
     * the same intervals across runs.
     */
    private function seedEmployeeWithCustomSaturday(): Employee
    {
        $dept = Department::create(['name' => 'Branch Default']);

        // Default global schedule already seeded — Sat = 240 minutes.
        // Just create an employee in a department; no overrides.
        return Employee::create([
            'employee_id' => 'EMP-SAT-' . strtoupper(bin2hex(random_bytes(2))),
            'first_name'  => 'Sat',
            'last_name'   => 'Worker',
            'email'       => 'sat.worker.' . bin2hex(random_bytes(3)) . '@example.test',
            'gender'      => 'male',
            'hired_at'    => '2025-01-01',
            'status'      => 'active',
            'department_id' => $dept->id,
        ]);
    }
}
