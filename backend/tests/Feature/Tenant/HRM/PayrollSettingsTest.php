<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Account;
use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollPeriod;
use App\Tenants\Modules\HRM\Services\PayrollService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 9 Item 4 — PayrollService consults hrm.payroll.* settings.
 *
 *   monthlyWorkHoursStandard():
 *     - Returns the configured value when set and > 0.
 *     - Falls back to 160 on unset / zero / non-numeric values.
 *
 *   defaultPayday():
 *     - Returns 1..31 settings; out-of-range or missing → 25.
 *
 *   fmsPostingEnabled():
 *     - Defaults true (the historic behaviour always posted).
 *     - false disables the journal post inside closePeriod().
 *
 *   closePeriod() with fms_posting_enabled=false:
 *     - Period flips to `closed`, `journal_entry_id` stays null,
 *       no JournalEntry rows are created.
 *
 *   resolvePayrollAccounts():
 *     - Per-tenant account-code overrides win over the config defaults.
 */
class PayrollSettingsTest extends TenantTestCase
{
    private PayrollService $service;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = app(PayrollService::class);
        $this->settings = app(SettingService::class);
    }

    public function test_monthly_hours_uses_setting_when_present(): void
    {
        $this->settings->set('hrm.payroll.monthly_work_hours_standard', 176, 'integer');
        $this->assertSame(176, $this->service->monthlyWorkHoursStandard());
    }

    public function test_monthly_hours_falls_back_to_default_on_zero(): void
    {
        $this->settings->set('hrm.payroll.monthly_work_hours_standard', 0, 'integer');
        $this->assertSame(160, $this->service->monthlyWorkHoursStandard());
    }

    public function test_monthly_hours_falls_back_to_default_on_non_numeric(): void
    {
        $this->settings->set('hrm.payroll.monthly_work_hours_standard', 'lots', 'string');
        $this->assertSame(160, $this->service->monthlyWorkHoursStandard());
    }

    public function test_default_payday_honors_in_range_setting(): void
    {
        $this->settings->set('hrm.payroll.default_payday', 15, 'integer');
        $this->assertSame(15, $this->service->defaultPayday());
    }

    public function test_default_payday_falls_back_when_out_of_range(): void
    {
        $this->settings->set('hrm.payroll.default_payday', 99, 'integer');
        $this->assertSame(25, $this->service->defaultPayday());
    }

    public function test_fms_posting_defaults_to_true(): void
    {
        $this->assertTrue($this->service->fmsPostingEnabled());
    }

    public function test_fms_posting_flag_false_disables_posting(): void
    {
        $this->settings->set('hrm.payroll.fms_posting_enabled', false, 'boolean');
        $this->assertFalse($this->service->fmsPostingEnabled());
    }

    public function test_close_period_skips_journal_when_posting_disabled(): void
    {
        $this->settings->set('hrm.payroll.fms_posting_enabled', false, 'boolean');

        // Need at least one payslip so postPayrollJournal would have something
        // to balance — proves the skip is happening at the gate, not because
        // the sum collapsed to zero.
        Employee::create([
            'employee_id' => 'EMP-PR-TEST',
            'first_name'  => 'Pay',
            'last_name'   => 'Tester',
            'email'       => 'pay.tester@example.test',
            'hired_at'    => '2025-01-01',
            'base_salary' => 3200,
            'status'      => 'active',
        ]);

        $period = $this->service->createPeriod([
            'name'       => 'March 2026',
            'start_date' => '2026-03-01',
            'end_date'   => '2026-03-31',
        ]);
        $this->service->processPeriod($period);

        // Snapshot existing journal-entry count — closing should not increase it.
        $beforeJournals = \DB::table('journal_entries')->count();

        $closed = $this->service->closePeriod($period);

        $this->assertSame('closed', $closed->status);
        $this->assertNull($closed->journal_entry_id);
        $this->assertSame($beforeJournals, \DB::table('journal_entries')->count());
    }

    public function test_resolve_payroll_accounts_honors_tenant_override(): void
    {
        // Override one of the four codes to a non-default value, then assert
        // closePeriod fails with that custom code in the missing list (since
        // the seeded CoA only has the defaults). Confirms the override is
        // routed through.
        $this->settings->set('hrm.payroll.account_wages_expense', 'EXP-CUSTOM-WAGES', 'string');

        Employee::create([
            'employee_id' => 'EMP-PR-TEST-2',
            'first_name'  => 'Pay',
            'last_name'   => 'Other',
            'email'       => 'pay.other@example.test',
            'hired_at'    => '2025-01-01',
            'base_salary' => 2400,
            'status'      => 'active',
        ]);

        // Ensure the other three default codes exist so they don't pollute
        // the missing-list assertion. Seeder already creates them on setUp.
        $this->assertTrue(Account::query()->where('code', 'LIA-TAX')->exists());

        $period = $this->service->createPeriod([
            'name'       => 'April 2026',
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-30',
        ]);
        $this->service->processPeriod($period);

        try {
            $this->service->closePeriod($period);
            $this->fail('Expected DomainException listing the custom code as missing.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('EXP-CUSTOM-WAGES', $e->getMessage());
        }
    }
}
