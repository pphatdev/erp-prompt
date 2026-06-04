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
 * Phase 3 - ESS payslip endpoints.
 *
 *   GET /api/v1/me/payslips
 *     - 404 when the caller has no linked Employee row.
 *     - 200 with the caller's payslips only (cannot see other employees').
 *     - admin (no employee row) still 404s here - this endpoint is ESS-only.
 *
 *   GET /api/v1/payslips/{id}/pdf
 *     - 403 when a self-service caller asks for someone else's payslip.
 *     - 200 application/pdf when caller is the owner OR has hrm.payroll.read.
 *
 * The PDF body itself is not parsed - asserting the Content-Type + non-empty
 * body is sufficient. Pdf rendering is barryvdh/laravel-dompdf's contract.
 */
class PayslipEssTest extends TenantTestCase
{
    private User $selfUser;
    private Employee $selfEmployee;
    private Employee $otherEmployee;
    private Payslip $selfPayslip;
    private Payslip $otherPayslip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->selfEmployee = Employee::create([
            'first_name' => 'Mine', 'last_name' => 'Owner',
            'email' => 'mine.owner@ess.example',
            'employee_id' => 'ESS-001', 'status' => 'active',
            'base_salary' => 3500.00,
        ]);
        $this->otherEmployee = Employee::create([
            'first_name' => 'Other', 'last_name' => 'Owner',
            'email' => 'other.owner@ess.example',
            'employee_id' => 'ESS-002', 'status' => 'active',
            'base_salary' => 7500.00,
        ]);

        $this->selfUser = User::create([
            'name' => 'Mine Owner User',
            'email' => 'mine.owner@ess.example',
            'password' => 'password',
        ]);
        $this->selfEmployee->update(['user_id' => $this->selfUser->id]);
        $this->selfUser->roles()->attach(Role::where('slug', 'employee')->firstOrFail());

        $period = PayrollPeriod::create([
            'name' => '2026-04', 'start_date' => '2026-04-01',
            'end_date' => '2026-04-30', 'status' => 'processed',
        ]);

        $this->selfPayslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $this->selfEmployee->id,
            'gross_salary'      => 3500.00, 'net_salary' => 3010.00,
            'earnings' => ['base' => 3500.00],
            'deductions' => ['tax' => 350.00, 'nssf' => 140.00],
        ]);
        $this->otherPayslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id'       => $this->otherEmployee->id,
            'gross_salary'      => 7500.00, 'net_salary' => 6450.00,
            'earnings' => ['base' => 7500.00],
            'deductions' => ['tax' => 750.00, 'nssf' => 300.00],
        ]);
    }

    // ---------- /me/payslips ------------------------------------------------

    public function test_me_payslips_returns_only_self_rows(): void
    {
        $response = $this->actingAs($this->selfUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/me/payslips');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->selfPayslip->id, $ids);
        $this->assertNotContains($this->otherPayslip->id, $ids);
    }

    public function test_me_payslips_404s_when_user_has_no_employee_link(): void
    {
        // Admin user from TenantTestCase setUp is linked to the seeded
        // admin@example.com employee; create a fresh user with no employee
        // row so we exercise the 404 branch.
        $orphan = User::create([
            'name' => 'Orphan', 'email' => 'orphan@ess.example', 'password' => 'password',
        ]);
        $orphan->roles()->attach(Role::where('slug', 'employee')->firstOrFail());

        $response = $this->actingAs($orphan, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/me/payslips');

        $response->assertStatus(404);
    }

    // ---------- /payslips/{id}/pdf -----------------------------------------

    public function test_pdf_returns_403_for_other_employee_payslip(): void
    {
        $response = $this->actingAs($this->selfUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->get("/api/v1/payslips/{$this->otherPayslip->id}/pdf");

        $response->assertStatus(403);
    }

    public function test_pdf_returns_pdf_for_own_payslip(): void
    {
        $response = $this->actingAs($this->selfUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->get("/api/v1/payslips/{$this->selfPayslip->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith('%PDF-', $response->getContent(),
            'Response body must be a PDF binary stream.');
    }

    public function test_pdf_returns_pdf_for_admin_on_other_employee(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->get("/api/v1/payslips/{$this->otherPayslip->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
