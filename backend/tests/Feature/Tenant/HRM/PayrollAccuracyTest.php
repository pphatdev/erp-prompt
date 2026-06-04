<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Services\PayrollService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 5 P1 — payroll accuracy fixtures.
 *
 * Locks the canonical math wired into `PayrollService::computeFor()` against
 * a salary brackets table. Without a PayrollPeriod the only inputs are the
 * employee's base salary, the 10% flat tax, and the 4% NSSF rate. With a
 * period, overtime + attendance can modify gross / deductions but every
 * test here uses the no-period overload so the bracket math stays pure.
 *
 *  Formula:
 *    gross      = base + bonus + overtime  (here: base only)
 *    tax        = round(gross * 0.10, 2)
 *    nssf       = round(gross * 0.04, 2)
 *    net        = round(gross - tax - nssf - absent - unpaid_leave, 2)
 *
 *  These tests are deliberately decimal-sensitive — any change to the rates
 *  or the rounding strategy must break this fixture deliberately.
 */
class PayrollAccuracyTest extends TenantTestCase
{
    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayrollService::class);
    }

    public function test_zero_base_salary_yields_zero_gross_tax_nssf_net(): void
    {
        $employee = $this->makeEmployee(0);

        $result = $this->service->computeFor($employee);

        $this->assertSame(0.0, $result['gross']);
        $this->assertSame(0.0, $result['deductions']['tax']);
        $this->assertSame(0.0, $result['deductions']['nssf']);
        $this->assertSame(0.0, $result['net']);
    }

    /**
     * @dataProvider salaryBracketProvider
     */
    public function test_salary_brackets_produce_expected_gross_tax_nssf_net(
        float $base,
        float $expectedGross,
        float $expectedTax,
        float $expectedNssf,
        float $expectedNet,
    ): void {
        $employee = $this->makeEmployee($base);

        $result = $this->service->computeFor($employee);

        $this->assertSame($expectedGross, $result['gross'],
            "Gross mismatch at base={$base}.");
        $this->assertSame($expectedTax, $result['deductions']['tax'],
            "Tax mismatch at base={$base}.");
        $this->assertSame($expectedNssf, $result['deductions']['nssf'],
            "NSSF mismatch at base={$base}.");
        $this->assertSame($expectedNet, $result['net'],
            "Net mismatch at base={$base}.");

        // Earnings should always have the base under 'base' and zero for
        // bonus + overtime when no period is supplied.
        $this->assertSame($base, $result['earnings']['base']);
        $this->assertSame(0.0, $result['earnings']['bonus']);
        $this->assertSame(0.0, $result['earnings']['overtime']);
    }

    public static function salaryBracketProvider(): array
    {
        // [ base, gross, tax (10%), nssf (4%), net (gross - tax - nssf) ]
        return [
            'flat 1000'          => [1000.00, 1000.00, 100.00, 40.00, 860.00],
            'flat 1500'          => [1500.00, 1500.00, 150.00, 60.00, 1290.00],
            'flat 2500'          => [2500.00, 2500.00, 250.00, 100.00, 2150.00],
            'flat 5000'          => [5000.00, 5000.00, 500.00, 200.00, 4300.00],
            'flat 12345.67'      => [12345.67, 12345.67, 1234.57, 493.83, 10617.27],
            'odd-cent 999.99'    => [999.99, 999.99, 100.00, 40.00, 859.99],
            'odd-cent 1234.56'   => [1234.56, 1234.56, 123.46, 49.38, 1061.72],
        ];
    }

    public function test_overtime_hourly_rate_reads_monthly_work_hours_setting(): void
    {
        // monthlyWorkHoursStandard() default is 160. Overtime divisor changes
        // when the setting is overridden — verify the accessor honors it.
        $settings = app(SettingService::class);

        $this->assertSame(160, $this->service->monthlyWorkHoursStandard());

        $settings->set('hrm.payroll.monthly_work_hours_standard', 200);
        $settings->flushCache();
        // Construct fresh so the accessor re-reads through the same SettingService.
        $this->assertSame(200, app(PayrollService::class)->monthlyWorkHoursStandard());

        // Zero / non-numeric values fall back to 160 (defensive — div-by-zero guard).
        $settings->set('hrm.payroll.monthly_work_hours_standard', 0);
        $settings->flushCache();
        $this->assertSame(160, app(PayrollService::class)->monthlyWorkHoursStandard());

        $settings->set('hrm.payroll.monthly_work_hours_standard', 'garbage');
        $settings->flushCache();
        $this->assertSame(160, app(PayrollService::class)->monthlyWorkHoursStandard());
    }

    public function test_default_payday_clamps_to_1_through_31(): void
    {
        $settings = app(SettingService::class);

        $this->assertSame(25, $this->service->defaultPayday(),
            'Untouched setting falls back to 25.');

        $settings->set('hrm.payroll.default_payday', 15);
        $settings->flushCache();
        $this->assertSame(15, app(PayrollService::class)->defaultPayday());

        $settings->set('hrm.payroll.default_payday', 0);
        $settings->flushCache();
        $this->assertSame(25, app(PayrollService::class)->defaultPayday(),
            'Out-of-range day falls back to 25.');

        $settings->set('hrm.payroll.default_payday', 32);
        $settings->flushCache();
        $this->assertSame(25, app(PayrollService::class)->defaultPayday());
    }

    private function makeEmployee(float $baseSalary): Employee
    {
        return Employee::create([
            'first_name' => 'Test', 'last_name' => 'Subject',
            'email' => "subject.{$baseSalary}@payroll.example",
            'employee_id' => 'PAY-' . uniqid(),
            'status' => 'active',
            'base_salary' => $baseSalary,
        ]);
    }
}
