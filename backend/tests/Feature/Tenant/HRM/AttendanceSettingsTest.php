<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Services\AttendanceService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Feature\TenantTestCase;

/**
 * Phase 9 Item 3 — AttendanceService consults hrm.attendance.* settings.
 *
 *  Geofence:
 *    - enable_geofencing OFF (default) skips enforcement even when the
 *      department row carries lat/lon.
 *    - enable_geofencing ON + within radius → clock-in succeeds.
 *    - enable_geofencing ON + outside radius → 422.
 *    - When the department has no per-row radius, the tenant-level
 *      `hrm.attendance.geofence_radius_meters` is used.
 *
 *  IP whitelist:
 *    - enable_ip_whitelisting OFF skips entirely.
 *    - enable_ip_whitelisting ON + no rules anywhere → fails closed.
 *    - Tenant `hrm.attendance.ip_whitelist` matches alone.
 *
 *  Auto clock-out:
 *    - autoCloseOpenSessions closes a stale row at check_in + window.
 *    - Fresh rows (< window) are untouched.
 */
class AttendanceSettingsTest extends TenantTestCase
{
    private AttendanceService $service;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = app(AttendanceService::class);
        $this->settings = app(SettingService::class);
    }

    public function test_geofence_off_skips_enforcement_even_with_department_coords(): void
    {
        // Default is off, but set explicitly to make the intent obvious.
        $this->settings->set('hrm.attendance.enable_geofencing', false, 'boolean');

        [$employee] = $this->seedEmployeeWithDepartment([
            'latitude' => 11.5564,
            'longitude' => 104.9282,
            'geofence_radius_meters' => 50,
        ]);

        // Clock-in payload lacks coords — would explode if geofence was on.
        $log = $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
        ]);

        $this->assertInstanceOf(AttendanceLog::class, $log);
        $this->assertNotNull($log->check_in);
    }

    public function test_geofence_on_within_radius_passes(): void
    {
        $this->settings->set('hrm.attendance.enable_geofencing', true, 'boolean');

        [$employee] = $this->seedEmployeeWithDepartment([
            'latitude' => 11.5564,
            'longitude' => 104.9282,
            'geofence_radius_meters' => 100,
        ]);

        // ~30m from the office origin.
        $log = $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'latitude'   => 11.5566,
            'longitude'  => 104.9283,
        ]);

        $this->assertNotNull($log->check_in);
    }

    public function test_geofence_on_outside_radius_throws_422(): void
    {
        $this->settings->set('hrm.attendance.enable_geofencing', true, 'boolean');

        [$employee] = $this->seedEmployeeWithDepartment([
            'latitude' => 11.5564,
            'longitude' => 104.9282,
            'geofence_radius_meters' => 50,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'latitude'   => 11.7000,  // ~16km off
            'longitude'  => 105.0000,
        ]);
    }

    public function test_geofence_on_uses_tenant_radius_when_department_has_none(): void
    {
        $this->settings->set('hrm.attendance.enable_geofencing', true, 'boolean');
        $this->settings->set('hrm.attendance.geofence_radius_meters', 10000, 'integer');

        [$employee] = $this->seedEmployeeWithDepartment([
            'latitude' => 11.5564,
            'longitude' => 104.9282,
            // No `geofence_radius_meters` — should pick up tenant 10km fallback.
        ]);

        // ~5km away — would fail the default 100m, passes under 10km override.
        $log = $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'latitude'   => 11.6000,
            'longitude'  => 104.9282,
        ]);

        $this->assertNotNull($log->check_in);
    }

    public function test_ip_whitelist_off_skips_enforcement(): void
    {
        $this->settings->set('hrm.attendance.enable_ip_whitelisting', false, 'boolean');

        [$employee] = $this->seedEmployeeWithDepartment([
            'attendance_ip_whitelist' => ['10.0.0.0/24'],
        ]);

        $log = $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'client_ip'  => '8.8.8.8',
        ]);

        $this->assertNotNull($log->check_in);
    }

    public function test_ip_whitelist_on_with_no_rules_anywhere_fails_closed(): void
    {
        $this->settings->set('hrm.attendance.enable_ip_whitelisting', true, 'boolean');
        $this->settings->set('hrm.attendance.ip_whitelist', '', 'string');

        [$employee] = $this->seedEmployeeWithDepartment(['attendance_ip_whitelist' => []]);

        $this->expectException(ValidationException::class);

        $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'client_ip'  => '10.0.0.5',
        ]);
    }

    public function test_tenant_ip_whitelist_alone_matches_request(): void
    {
        $this->settings->set('hrm.attendance.enable_ip_whitelisting', true, 'boolean');
        $this->settings->set('hrm.attendance.ip_whitelist', '10.0.0.0/24, 192.168.1.5', 'string');

        [$employee] = $this->seedEmployeeWithDepartment(['attendance_ip_whitelist' => []]);

        $log = $this->service->clockIn($employee, [
            'clock_time' => '2026-03-02 09:00:00',
            'client_ip'  => '10.0.0.42',
        ]);

        $this->assertNotNull($log->check_in);
    }

    public function test_auto_close_open_sessions_closes_stale_rows_at_window(): void
    {
        $this->settings->set('hrm.attendance.auto_clock_out_hours', 12, 'integer');

        [$employee] = $this->seedEmployeeWithDepartment([]);

        // Pretend the clock-in happened 18h ago — well past the 12h window.
        $stale = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date'        => CarbonImmutable::now()->subHours(18)->toDateString(),
            'check_in'    => CarbonImmutable::now()->subHours(18),
            'status'      => AttendanceLog::STATUS_PRESENT,
        ]);

        $closedCount = $this->service->autoCloseOpenSessions();
        $this->assertSame(1, $closedCount);

        $stale->refresh();
        $this->assertNotNull($stale->check_out);
        // Closed at check_in + 12h (window), not "now".
        $expectedClose = CarbonImmutable::parse($stale->check_in)->addHours(12);
        $this->assertSame(
            $expectedClose->toDateTimeString(),
            CarbonImmutable::parse($stale->check_out)->toDateTimeString(),
        );
    }

    public function test_auto_close_leaves_fresh_rows_alone(): void
    {
        $this->settings->set('hrm.attendance.auto_clock_out_hours', 12, 'integer');

        [$employee] = $this->seedEmployeeWithDepartment([]);

        // Clocked in 2h ago — well inside the 12h window.
        $fresh = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date'        => CarbonImmutable::now()->toDateString(),
            'check_in'    => CarbonImmutable::now()->subHours(2),
            'status'      => AttendanceLog::STATUS_PRESENT,
        ]);

        $closedCount = $this->service->autoCloseOpenSessions();
        $this->assertSame(0, $closedCount);

        $fresh->refresh();
        $this->assertNull($fresh->check_out);
    }

    /**
     * Seed a department (with the given overrides) + a single employee
     * pinned to it. Tests pass an empty array when they only need the
     * employee.
     */
    private function seedEmployeeWithDepartment(array $departmentAttrs): array
    {
        $department = Department::create(array_merge([
            'name' => 'Engineering',
            'code' => 'ENG',
        ], $departmentAttrs));

        $employee = Employee::create([
            'department_id' => $department->id,
            'employee_id'   => 'EMP-AT-TEST',
            'first_name'    => 'Anna',
            'last_name'     => 'Tester',
            'email'         => 'anna.tester@example.test',
            'hired_at'      => '2025-01-01',
            'status'        => 'active',
        ]);

        return [$employee, $department];
    }
}
