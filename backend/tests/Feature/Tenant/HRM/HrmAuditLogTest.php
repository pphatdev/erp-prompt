<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Payslip;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use App\Tenants\Modules\HRM\Services\LeaveService;
use App\Tenants\Modules\HRM\Services\PayrollService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Feature\TenantTestCase;

/**
 * Phase 5 — Auditable trail assertions on the four highest-impact HRM
 * actions: hire, terminate, leave approve, payroll process.
 *
 * The Auditable trait writes to `Log::info('Audit Log: <action>', [...])`
 * (no DB table yet — see `app/Models/Traits/Auditable.php`). We capture
 * those calls with `Log::spy()` and assert the expected `action` + `model`
 * pair was logged at least once. Capturing happens AFTER the per-test
 * fixture is built so we only see the audit lines triggered by the
 * action-under-test, not the setUp() seeding noise.
 */
class HrmAuditLogTest extends TenantTestCase
{
    public function test_hire_writes_audit_log_for_created_employee(): void
    {
        $service = app(EmployeeService::class);

        Log::spy();
        $employee = $service->createEmployee([
            'first_name' => 'Audit', 'last_name' => 'Hire',
            'email' => 'audit.hire@example.com',
            'status' => 'active',
            'base_salary' => 3000.00,
        ]);

        $this->assertAuditLogged('create', Employee::class, $employee->id);
    }

    public function test_terminate_writes_audit_logs_for_update_then_delete(): void
    {
        $service = app(EmployeeService::class);
        $employee = Employee::create([
            'first_name' => 'Term', 'last_name' => 'Inate',
            'email' => 'term.inate@example.com',
            'employee_id' => 'TERM-001',
            'status' => 'active',
        ]);

        Log::spy();
        $service->terminateEmployee($employee);

        // terminateEmployee both updates status to 'terminated' AND soft-deletes
        // the row. The Auditable trait fires `updated` + `deleted` events.
        $this->assertAuditLogged('update', Employee::class, $employee->id);
        $this->assertAuditLogged('delete', Employee::class, $employee->id);
    }

    public function test_leave_approve_writes_audit_log_for_status_update(): void
    {
        $service = app(LeaveService::class);
        $employee = Employee::create([
            'first_name' => 'Leave', 'last_name' => 'Owner',
            'email' => 'leave.owner@example.com',
            'employee_id' => 'LEAVE-001',
            'status' => 'active',
        ]);
        $leaveType = LeaveType::create([
            'name' => 'Annual', 'annual_allowance' => 20,
        ]);
        $leave = Leave::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date'    => '2026-03-09',
            'end_date'      => '2026-03-09',
            'days'          => 1.0,
            'status'        => 'pending',
        ]);

        Log::spy();
        $service->approve($leave);

        $this->assertAuditLogged('update', Leave::class, $leave->id);
    }

    public function test_payroll_process_writes_audit_logs_for_each_payslip_create(): void
    {
        $service = app(PayrollService::class);

        // One active employee → one payslip → one 'create' audit row.
        $employee = Employee::create([
            'first_name' => 'Process', 'last_name' => 'Me',
            'email' => 'process.me@example.com',
            'employee_id' => 'PAY-001',
            'status' => 'active',
            'base_salary' => 2000.00,
        ]);
        $period = PayrollPeriod::create([
            'name' => '2026-03', 'start_date' => '2026-03-01',
            'end_date' => '2026-03-31', 'status' => 'draft',
        ]);

        Log::spy();
        $payslips = $service->processPeriod($period);

        // PayrollPeriod transitioned draft → processed and a payslip was
        // created for every active employee (TenantDatabaseSeeder pre-seeds
        // an admin + base employee, plus the one we just made). The audit
        // trail must contain the period update + at least our payslip.
        $this->assertAuditLogged('update', PayrollPeriod::class, $period->id);
        $myPayslip = $payslips->firstWhere('employee_id', $employee->id);
        $this->assertNotNull($myPayslip, 'processPeriod must produce a payslip for the test employee.');
        $this->assertAuditLogged('create', Payslip::class, $myPayslip->id);
    }

    /**
     * Asserts `Log::info` was called at least once with a message of
     * "Audit Log: {$action}" and a payload describing the given model+id.
     */
    private function assertAuditLogged(string $action, string $modelClass, string $modelId): void
    {
        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context = null) use ($action, $modelClass, $modelId) {
                if ($message !== "Audit Log: {$action}") {
                    return false;
                }
                if (!is_array($context)) {
                    return false;
                }
                return ($context['model'] ?? null) === $modelClass
                    && ($context['id'] ?? null) === $modelId;
            })->atLeast()->once();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
