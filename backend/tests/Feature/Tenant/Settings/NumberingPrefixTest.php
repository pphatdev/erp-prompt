<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Settings;

use App\Models\Central\Tenant;
use App\Models\Tenant\Application;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Setting;
use App\Support\GenerationRetry;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\TenantTestCase;
use Tests\TestCase;

class NumberingPrefixTest extends TenantTestCase
{
    public function test_employee_generator_respects_configured_prefix(): void
    {
        Setting::updateOrCreate(
            ['key' => 'numbering.employee_id_prefix'],
            ['value' => 'ACME-', 'group' => 'numbering', 'type' => 'string']
        );
        // Refresh the SettingService cache (it reads from cache via all()).
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->all();

        $first = app(RecruitmentService::class)->generateNextEmployeeId();

        $this->assertStringStartsWith('ACME-', $first);
        $this->assertSame('ACME-0000', $first, 'First auto-issued id must be zero-padded to 4 digits.');
    }

    public function test_employee_generator_falls_back_to_default_when_setting_missing(): void
    {
        Setting::where('key', 'numbering.employee_id_prefix')->delete();
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->all();

        $code = app(RecruitmentService::class)->generateNextEmployeeId();

        $this->assertSame('TT-0000', $code);
    }

    public function test_candidate_code_respects_configured_prefix_and_resets_per_month(): void
    {
        Setting::updateOrCreate(
            ['key' => 'numbering.candidate_code_prefix'],
            ['value' => 'JOB-', 'group' => 'numbering', 'type' => 'string']
        );
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->all();

        $may = Application::generateCandidateCode('2026-05-15');
        $jun = Application::generateCandidateCode('2026-06-01');

        $this->assertSame('JOB-202605-001', $may);
        $this->assertSame('JOB-202606-001', $jun, 'Sequence resets every month.');
    }

    public function test_employee_create_retries_on_unique_violation(): void
    {
        // Seed an existing TT-0000 so the generator's next attempt is TT-0001.
        Employee::create([
            'first_name' => 'Seed', 'last_name' => 'Existing',
            'email' => 'seed@test.com',
            'employee_id' => 'TT-0001', // pre-claim the next slot
            'hire_date' => '2024-01-01',
        ]);

        // Inject one collision: the first time createEmployee runs, the
        // generator returns TT-0001 (matches above), so the insert throws
        // 23505. Retry should recompute MAX -> TT-0002 and succeed.
        $svc = app(EmployeeService::class);

        $emp = $svc->createEmployee([
            'first_name' => 'New', 'last_name' => 'Hire',
            'email' => 'new@test.com',
            'hire_date' => '2024-01-01',
        ]);

        $this->assertSame('TT-0002', $emp->employee_id,
            'Retry must skip past the pre-claimed TT-0001 and land on TT-0002.');
    }

    public function test_generation_retry_helper_recovers_from_transient_unique_violation(): void
    {
        $attempts = 0;
        $result = GenerationRetry::handle(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new \Illuminate\Database\UniqueConstraintViolationException(
                    'pgsql', 'INSERT INTO t', [], new \PDOException('UNIQUE violation')
                );
            }
            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $attempts);
    }

    public function test_generation_retry_rethrows_after_exhausting_attempts(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        GenerationRetry::handle(function () {
            throw new \Illuminate\Database\UniqueConstraintViolationException(
                'pgsql', 'INSERT INTO t', [], new \PDOException('UNIQUE violation')
            );
        }, attempts: 3);
    }

    public function test_generation_retry_does_not_swallow_unrelated_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a unique violation');

        GenerationRetry::handle(function () {
            throw new \RuntimeException('not a unique violation');
        });
    }
}


/**
 * Cross-tenant isolation check for the numbering prefix - lives as a
 * standalone TestCase because TenantTestCase boots into a single tenant.
 */
class NumberingPrefixCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_b_prefix_does_not_leak_to_tenant_a(): void
    {
        $tenantA = Tenant::create(['id' => 'num-a', 'handle' => 'num-a', 'name' => 'Num A']);
        $tenantB = Tenant::create(['id' => 'num-b', 'handle' => 'num-b', 'name' => 'Num B']);

        tenancy()->initialize($tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        Setting::updateOrCreate(
            ['key' => 'numbering.employee_id_prefix'],
            ['value' => 'A-CO-', 'group' => 'numbering', 'type' => 'string']
        );
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->all();
        $codeA = app(RecruitmentService::class)->generateNextEmployeeId();

        tenancy()->end();
        tenancy()->initialize($tenantB);
        $this->seed(TenantDatabaseSeeder::class);
        Setting::updateOrCreate(
            ['key' => 'numbering.employee_id_prefix'],
            ['value' => 'B-CO-', 'group' => 'numbering', 'type' => 'string']
        );
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->all();
        $codeB = app(RecruitmentService::class)->generateNextEmployeeId();

        $this->assertStringStartsWith('A-CO-', $codeA);
        $this->assertStringStartsWith('B-CO-', $codeB);
        $this->assertSame('A-CO-0000', $codeA);
        $this->assertSame('B-CO-0000', $codeB,
            'Both tenants must independently start at 0000 - sequence does not leak.');

        tenancy()->end();
    }
}
