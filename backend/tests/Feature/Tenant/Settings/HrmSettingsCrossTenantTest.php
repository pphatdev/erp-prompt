<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Settings;

use App\Models\Central\Tenant;
use App\Models\Tenant\Setting;
use App\Tenants\Modules\Settings\Services\SettingService;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 Item 2 - Multi-tenant isolation audit for hrm.* settings.
 *
 * Standalone TestCase (not TenantTestCase) because we need to boot two
 * separate tenant connections and assert that a setting written on
 * Tenant A never bleeds into Tenant B.
 *
 * The setting changed on Tenant A is a representative payroll value
 * (default_payday) so the same isolation guarantee covers every other
 * hrm.* row in the catalogue.
 */
class HrmSettingsCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrm_setting_write_on_tenant_a_does_not_bleed_to_tenant_b(): void
    {
        $tenantA = Tenant::create(['id' => 'hrm-a', 'handle' => 'hrm-a', 'name' => 'HRM A']);
        $tenantB = Tenant::create(['id' => 'hrm-b', 'handle' => 'hrm-b', 'name' => 'HRM B']);

        // Tenant A: change payday to 10, work hours to 200, and the wages
        // expense account code. All others should remain default.
        tenancy()->initialize($tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $settingsA = app(SettingService::class);
        $settingsA->set('hrm.payroll.default_payday', 10);
        $settingsA->set('hrm.payroll.monthly_work_hours_standard', 200);
        $settingsA->set('hrm.payroll.account_wages_expense', 'A-EXP-WAGES');
        $settingsA->flushCache();

        $this->assertSame(10, $settingsA->get('hrm.payroll.default_payday'));
        $this->assertSame(200, $settingsA->get('hrm.payroll.monthly_work_hours_standard'));
        $this->assertSame('A-EXP-WAGES', $settingsA->get('hrm.payroll.account_wages_expense'));
        $this->assertSame(
            (int) Setting::query()->where('tenant_id', 'hrm-a')->count(),
            (int) Setting::query()->count(),
            'Setting query inside Tenant A scope must only see Tenant A rows.'
        );
        tenancy()->end();

        // Tenant B: cold-boot through ensureDefaults() and verify all values
        // are the catalogue defaults - none of Tenant A's writes are visible.
        tenancy()->initialize($tenantB);
        $this->seed(TenantDatabaseSeeder::class);
        $settingsB = app(SettingService::class);

        $this->assertSame(25, $settingsB->get('hrm.payroll.default_payday'),
            'Tenant B must see the default payday (25), not Tenant A\'s value (10).');
        $this->assertSame(160, $settingsB->get('hrm.payroll.monthly_work_hours_standard'),
            'Tenant B must see the default work hours (160), not Tenant A\'s value (200).');
        $this->assertSame('EXP-WAGES', $settingsB->get('hrm.payroll.account_wages_expense'),
            'Tenant B must see the default wages-expense code, not Tenant A\'s value.');

        // Sanity: Tenant B can write its own value and it doesn't surface
        // on Tenant A's connection.
        $settingsB->set('hrm.payroll.default_payday', 28);
        $this->assertSame(28, $settingsB->get('hrm.payroll.default_payday'));
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $settingsA = app(SettingService::class);
        $this->assertSame(10, $settingsA->get('hrm.payroll.default_payday'),
            'Tenant A\'s previously-written value must be preserved after Tenant B writes.');
        tenancy()->end();
    }

    public function test_hrm_setting_row_carries_tenant_scope(): void
    {
        $tenantA = Tenant::create(['id' => 'hrm-iso-a', 'handle' => 'hrm-iso-a', 'name' => 'Iso A']);
        $tenantB = Tenant::create(['id' => 'hrm-iso-b', 'handle' => 'hrm-iso-b', 'name' => 'Iso B']);

        tenancy()->initialize($tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        app(SettingService::class)->set('hrm.leave.allow_negative_balance', true);
        $rowA = Setting::query()->where('key', 'hrm.leave.allow_negative_balance')->firstOrFail();
        $this->assertSame('hrm-iso-a', $rowA->tenant_id,
            'Setting row written under Tenant A must carry that tenant_id.');
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->seed(TenantDatabaseSeeder::class);
        $rowB = Setting::query()->where('key', 'hrm.leave.allow_negative_balance')->firstOrFail();
        $this->assertSame('hrm-iso-b', $rowB->tenant_id,
            'Setting row read under Tenant B must carry Tenant B\'s tenant_id - not Tenant A\'s.');
        $this->assertFalse((bool) $rowB->value,
            'Tenant B must see the default false - Tenant A\'s true does not leak.');
        tenancy()->end();
    }
}
