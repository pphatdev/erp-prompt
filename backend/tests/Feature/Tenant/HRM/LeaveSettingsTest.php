<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveType;
use App\Tenants\Modules\HRM\Services\LeaveService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Tests\Feature\TenantTestCase;

/**
 * Phase 9 Item 2 — LeaveService consults hrm.leave.* settings.
 *
 *  - countWorkingDays() honors `hrm.leave.standard_work_week`
 *      • default Mon–Fri (1..5) skips Sat/Sun
 *      • override to Sun–Thu (7,1,2,3,4) excludes Fri instead
 *      • a fully-weekend span returns 0 under the default week
 *  - submitRequest() honors `hrm.leave.allow_negative_balance`
 *      • false (default) throws when requested > remaining
 *      • true bypasses the throw — used for emergency / unpaid leave
 */
class LeaveSettingsTest extends TenantTestCase
{
    private LeaveService $service;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = app(LeaveService::class);
        $this->settings = app(SettingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_default_work_week_skips_weekends(): void
    {
        // 2026-02-02 (Mon) → 2026-02-08 (Sun) = 7 calendar days, 5 working days.
        $start = CarbonImmutable::parse('2026-02-02');
        $end   = CarbonImmutable::parse('2026-02-08');

        $this->assertSame(5, $this->service->countWorkingDays($start, $end));
    }

    public function test_weekend_only_span_returns_zero_working_days(): void
    {
        // 2026-02-07 (Sat) → 2026-02-08 (Sun) — neither in default Mon–Fri week.
        $start = CarbonImmutable::parse('2026-02-07');
        $end   = CarbonImmutable::parse('2026-02-08');

        $this->assertSame(0, $this->service->countWorkingDays($start, $end));
    }

    public function test_sun_thu_work_week_excludes_friday(): void
    {
        $this->settings->set('hrm.leave.standard_work_week', [7, 1, 2, 3, 4], 'json');

        // 2026-02-02 (Mon) → 2026-02-08 (Sun) = 5 days under Mon-Fri, 5 days
        // under Sun–Thu (Sun + Mon + Tue + Wed + Thu — Fri excluded, Sat
        // excluded). Sanity-check by trimming one Fri-only day instead.
        $start = CarbonImmutable::parse('2026-02-06'); // Fri
        $end   = CarbonImmutable::parse('2026-02-06'); // Fri

        // Under Sun-Thu work week, a single Friday is NOT a working day.
        $this->assertSame(0, $this->service->countWorkingDays($start, $end));

        // Sunday IS a working day under that override.
        $sun = CarbonImmutable::parse('2026-02-08');
        $this->assertSame(1, $this->service->countWorkingDays($sun, $sun));
    }

    public function test_garbage_setting_value_falls_back_to_mon_fri(): void
    {
        // String / non-array values shouldn't crash; should fall back.
        $this->settings->set('hrm.leave.standard_work_week', 'not-an-array', 'string');

        $start = CarbonImmutable::parse('2026-02-02'); // Mon
        $end   = CarbonImmutable::parse('2026-02-08'); // Sun

        $this->assertSame(5, $this->service->countWorkingDays($start, $end));
    }

    public function test_submit_request_throws_when_balance_insufficient_by_default(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 2);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient leave balance');

        $this->service->submitRequest([
            'employee_id'    => $employee->id,
            'leave_type_id'  => $type->id,
            'start_date'     => '2026-03-02', // Mon
            'end_date'       => '2026-03-06', // Fri — 5 working days
            'reason'         => 'unit test',
        ]);
    }

    public function test_submit_request_bypasses_balance_when_allow_negative_balance_enabled(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 2);

        $this->settings->set('hrm.leave.allow_negative_balance', true, 'boolean');

        // Same 5-working-day request that just threw — should now pass.
        $leave = $this->service->submitRequest([
            'employee_id'    => $employee->id,
            'leave_type_id'  => $type->id,
            'start_date'     => '2026-03-02',
            'end_date'       => '2026-03-06',
            'reason'         => 'emergency, balance allowed to go negative',
        ]);

        $this->assertNotNull($leave);
        $this->assertSame(5.0, (float) $leave->days);
    }

    public function test_submit_request_blocks_when_below_min_notice_days(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.min_notice_days', 7, 'integer');

        // Freeze "now" at a Monday so notice math is deterministic.
        Carbon::setTestNow('2030-03-04 09:00:00');
        CarbonImmutable::setTestNow('2030-03-04 09:00:00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('at least 7');

        // Start 3 days away — below the 7-day threshold.
        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-07',
            'end_date'      => '2030-03-07',
            'reason'        => 'short notice',
        ]);
    }

    public function test_submit_request_allows_when_meeting_min_notice_days(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.min_notice_days', 7, 'integer');

        Carbon::setTestNow('2030-03-04 09:00:00'); // Mon
        CarbonImmutable::setTestNow('2030-03-04 09:00:00');

        // Start 10 days out — comfortably above the 7-day threshold.
        $leave = $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-14',
            'end_date'      => '2030-03-14',
            'reason'        => 'planned ahead',
        ]);

        $this->assertNotNull($leave);
    }

    public function test_submit_request_blocks_when_exceeding_max_consecutive_days(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 60);
        $this->settings->set('hrm.leave.max_consecutive_days', 3, 'integer');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('3 consecutive');

        // 5 working days (Mon-Fri) > 3 cap.
        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04', // Mon
            'end_date'      => '2030-03-08', // Fri
            'reason'        => 'too long',
        ]);
    }

    public function test_submit_request_requires_attachment_above_threshold(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.attachment_required_days', 3, 'integer');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('supporting document');

        // 3 working days, no attachment supplied.
        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04', // Mon
            'end_date'      => '2030-03-06', // Wed
            'reason'        => 'flu',
        ]);
    }

    public function test_submit_request_accepts_attachment_above_threshold(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.attachment_required_days', 3, 'integer');

        $leave = $this->service->submitRequest([
            'employee_id'     => $employee->id,
            'leave_type_id'   => $type->id,
            'start_date'      => '2030-03-04',
            'end_date'        => '2030-03-06',
            'reason'          => 'flu — cert attached',
            'attachment_path' => 'leaves/2030/medical-cert.pdf',
        ]);

        $this->assertSame('leaves/2030/medical-cert.pdf', $leave->attachment_path);
    }

    public function test_submit_request_auto_approves_below_threshold(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.auto_approve_days', 2, 'integer');

        // 2 working days <= 2-day auto-approve cap.
        $leave = $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04', // Mon
            'end_date'      => '2030-03-05', // Tue
            'reason'        => 'short trip',
        ]);

        $this->assertSame('approved', $leave->status);
    }

    public function test_submit_request_does_not_auto_approve_above_threshold(): void
    {
        [$employee, $type] = $this->seedEmployeeAndLeaveType(annualAllowance: 30);
        $this->settings->set('hrm.leave.auto_approve_days', 2, 'integer');

        // 3 working days > 2-day cap — workflow path.
        $leave = $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04', // Mon
            'end_date'      => '2030-03-06', // Wed
            'reason'        => 'longer trip',
        ]);

        $this->assertNotSame('approved', $leave->status);
    }

    private function seedEmployeeAndLeaveType(int $annualAllowance): array
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-LV-TEST',
            'first_name'  => 'Leah',
            'last_name'   => 'Tester',
            'email'       => 'leah.tester@example.test',
            'hired_at'    => '2025-01-01',
            'status'      => 'active',
        ]);

        $type = LeaveType::create([
            'name'             => 'Annual',
            'annual_allowance' => $annualAllowance,
        ]);

        return [$employee, $type];
    }
}
