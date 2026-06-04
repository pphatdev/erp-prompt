<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Central\Tenant;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Payslip;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 P0 — HRM tenancy isolation.
 *
 * Boots two tenant DBs, writes employee / leave / payslip rows on Tenant A,
 * then asserts that Tenant B's connection cannot enumerate or find any of
 * them. Verifies the `BelongsToTenant` global scope is wired on each HRM
 * model. Lives outside `TenantTestCase` because that base class boots a
 * single tenant and runs each test inside it.
 */
class HrmTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'hrm-iso-a', 'handle' => 'hrm-iso-a', 'name' => 'HRM Iso A']);
        $this->tenantB = Tenant::create(['id' => 'hrm-iso-b', 'handle' => 'hrm-iso-b', 'name' => 'HRM Iso B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_employees(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $employeeA = Employee::create([
            'first_name' => 'Alpha', 'last_name' => 'One',
            'email' => 'alpha.one@hrm-iso-a.example',
            'employee_id' => 'A-001', 'status' => 'active',
            'base_salary' => 1234.56,
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Employee::count(),
            'Tenant B must not see Tenant A employees through the global scope.');
        $this->assertNull(Employee::find($employeeA->id),
            'Tenant B must not be able to resolve Tenant A employee by UUID.');
    }

    public function test_tenant_b_cannot_see_tenant_a_leaves(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $employeeA = Employee::create([
            'first_name' => 'Alpha', 'last_name' => 'Two',
            'email' => 'alpha.two@hrm-iso-a.example',
            'employee_id' => 'A-002', 'status' => 'active',
        ]);
        $leaveTypeA = LeaveType::create([
            'name' => 'Annual', 'annual_allowance' => 20,
        ]);
        $leaveA = Leave::create([
            'employee_id'   => $employeeA->id,
            'leave_type_id' => $leaveTypeA->id,
            'start_date'    => '2026-01-05',
            'end_date'      => '2026-01-05',
            'days'          => 1.0,
            'reason'        => 'Tenant A confidential',
            'status'        => 'pending',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Leave::count(),
            'Tenant B must not enumerate Tenant A leave rows.');
        $this->assertNull(Leave::find($leaveA->id),
            'Tenant B must not resolve Tenant A leave by id.');
        $this->assertSame(0, LeaveType::query()->where('name', 'Annual')->where('annual_allowance', 20)->count(),
            'Tenant B catalog must not contain Tenant A custom leave type.');
    }

    public function test_tenant_b_cannot_see_tenant_a_payslips(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);
        $employeeA = Employee::create([
            'first_name' => 'Alpha', 'last_name' => 'Three',
            'email' => 'alpha.three@hrm-iso-a.example',
            'employee_id' => 'A-003', 'status' => 'active',
            'base_salary' => 5000.00,
        ]);
        $periodA = PayrollPeriod::create([
            'name' => '2026-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', 'status' => 'draft',
        ]);
        $payslipA = Payslip::create([
            'payroll_period_id' => $periodA->id,
            'employee_id'       => $employeeA->id,
            'gross_salary'      => 5000.00,
            'net_salary'        => 4300.00,
            'earnings'          => ['base' => 5000.00],
            'deductions'        => ['tax' => 500.00, 'nssf' => 200.00],
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Payslip::count(),
            'Tenant B must not enumerate Tenant A payslips.');
        $this->assertNull(Payslip::find($payslipA->id),
            'Tenant B must not resolve Tenant A payslip by id.');
        $this->assertSame(0, PayrollPeriod::query()->where('name', '2026-01')->count(),
            'Tenant B must not see Tenant A payroll periods.');
    }
}
