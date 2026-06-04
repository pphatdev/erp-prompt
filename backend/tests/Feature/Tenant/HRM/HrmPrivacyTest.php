<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Payslip;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Tests\Feature\TenantTestCase;

/**
 * Phase 5 P0 — HRM privacy.
 *
 * Asserts that a standard self-service `employee` (no `hrm.payroll.read`)
 *  - sees `baseSalary` / `bankName` / `bankAccountName` as null on every
 *    employee record (including others'); `bankAccountNumber` likewise null.
 *  - cannot enumerate or read another employee's payslip — the index
 *    force-filters to their own employee_id; `show` 403s on others.
 *  - CAN read their own payslip when their `hrm.payslip.read.self`
 *    permission is intact.
 *
 * Admin (full grant) sees real values + can list / read every payslip.
 */
class HrmPrivacyTest extends TenantTestCase
{
    private User $selfServiceUser;
    private Employee $selfEmployee;
    private Employee $otherEmployee;
    private Payslip $selfPayslip;
    private Payslip $otherPayslip;

    protected function setUp(): void
    {
        parent::setUp();

        // Two employees with distinct salaries + bank details.
        $this->selfEmployee = Employee::create([
            'first_name' => 'Self', 'last_name' => 'Service',
            'email' => 'self.service@privacy.example',
            'employee_id' => 'PRIV-001', 'status' => 'active',
            'base_salary' => 4200.50,
            'bank_name' => 'Self Bank',
            'bank_account_name' => 'Self Service',
            'bank_account_number' => '111122223333',
        ]);
        $this->otherEmployee = Employee::create([
            'first_name' => 'Other', 'last_name' => 'Person',
            'email' => 'other.person@privacy.example',
            'employee_id' => 'PRIV-002', 'status' => 'active',
            'base_salary' => 9999.99,
            'bank_name' => 'Other Bank',
            'bank_account_name' => 'Other Person',
            'bank_account_number' => '999988887777',
        ]);

        // Self-service user linked to the first employee row.
        $this->selfServiceUser = User::create([
            'name' => 'Self Service User',
            'email' => 'self.service@privacy.example',
            'password' => 'password',
        ]);
        // Wire the User -> Employee link so PayslipPolicy::ownsPayslip
        // resolves correctly.
        $this->selfEmployee->update(['user_id' => $this->selfServiceUser->id]);
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $this->selfServiceUser->roles()->attach($employeeRole);

        $period = PayrollPeriod::create([
            'name' => '2026-02', 'start_date' => '2026-02-01',
            'end_date' => '2026-02-28', 'status' => 'processed',
        ]);

        $this->selfPayslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $this->selfEmployee->id,
            'gross_salary'      => 4200.50, 'net_salary' => 3612.43,
            'earnings'   => ['base' => 4200.50],
            'deductions' => ['tax' => 420.05, 'nssf' => 168.02],
        ]);
        $this->otherPayslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $this->otherEmployee->id,
            'gross_salary'      => 9999.99, 'net_salary' => 8599.99,
            'earnings'   => ['base' => 9999.99],
            'deductions' => ['tax' => 1000.00, 'nssf' => 400.00],
        ]);
    }

    // ---------- baseSalary / bank fields are masked for non-payroll readers --

    public function test_self_service_user_sees_null_compensation_on_other_employee(): void
    {
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson("/api/v1/employees/{$this->otherEmployee->id}");

        // Self-service callers without `hrm.employee.read` are gated by the
        // EmployeePolicy — they can ONLY see their own profile. Hitting
        // another employee's id is forbidden.
        $response->assertStatus(403);
    }

    public function test_self_service_user_sees_null_compensation_on_own_employee(): void
    {
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson("/api/v1/employees/{$this->selfEmployee->id}");

        $response->assertStatus(200);
        // Self-service callers do not hold `hrm.payroll.read`, so even on
        // their OWN record EmployeeResource masks compensation fields. They
        // see their salary only via /payslips.
        $response->assertJsonPath('data.baseSalary', null);
        $response->assertJsonPath('data.bankName', null);
        $response->assertJsonPath('data.bankAccountName', null);
        $response->assertJsonPath('data.bankAccountNumber', null);
    }

    public function test_admin_sees_full_compensation_with_last_4_account_mask(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson("/api/v1/employees/{$this->otherEmployee->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.baseSalary', 9999.99);
        $response->assertJsonPath('data.bankName', 'Other Bank');
        $response->assertJsonPath('data.bankAccountName', 'Other Person');
        // Account number is masked to last 4 digits even for admins — never
        // expose the full number in resource serialization.
        $masked = $response->json('data.bankAccountNumber');
        $this->assertIsString($masked);
        $this->assertStringEndsWith('7777', $masked);
        $this->assertStringNotContainsString('999988887777', $masked);
    }

    // ---------- Payslip index force-filters to the caller's employee_id ------

    public function test_self_service_payslip_index_only_returns_own_rows(): void
    {
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/payslips');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->selfPayslip->id, $ids, 'Caller must see their own payslip.');
        $this->assertNotContains($this->otherPayslip->id, $ids,
            'Caller must NOT see another employee\'s payslip via the index.');
    }

    public function test_self_service_payslip_index_ignores_other_employee_filter(): void
    {
        // Even when the caller fakes ?employeeId=<other-id>, the controller
        // must drop the filter and force-scope to their own rows.
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/payslips?employeeId=' . $this->otherEmployee->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->otherPayslip->id, $ids);
        $this->assertContains($this->selfPayslip->id, $ids);
    }

    public function test_self_service_user_cannot_read_other_employee_payslip(): void
    {
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson("/api/v1/payslips/{$this->otherPayslip->id}");

        $response->assertStatus(403);
    }

    public function test_self_service_user_can_read_own_payslip(): void
    {
        $response = $this->actingAs($this->selfServiceUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson("/api/v1/payslips/{$this->selfPayslip->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->selfPayslip->id);
        $response->assertJsonPath('data.grossSalary', 4200.50);
    }
}
