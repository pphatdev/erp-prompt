<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Jobs\ProcessPayrollPeriodJob;
use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollPeriod;
use App\Tenants\Modules\HRM\Services\PayrollService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\TenantTestCase;

/**
 * Phase 3 - large-tenant payroll queueing.
 *
 *   < 200 active employees: PayrollPeriodController@process runs the
 *   synchronous PayrollService::processPeriod and returns the payslips
 *   inline. Period flips draft -> processed.
 *
 *   >= 200 active employees: controller dispatches ProcessPayrollPeriodJob
 *   and returns 202 with the period in `processing`. The job (mocked via
 *   Queue::fake in this test) is responsible for the final draft ->
 *   processed transition.
 *
 *   Job::handle covered separately: only runs when period.status =
 *   `processing`; rolls back to `draft` on exception.
 */
class PayrollQueueThresholdTest extends TenantTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_small_tenant_runs_inline_and_returns_payslips(): void
    {
        Queue::fake();

        $period = PayrollPeriod::create([
            'name' => '2026-07', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => 'draft',
        ]);

        // Seed 2 active employees (well below the 200 threshold).
        Employee::create([
            'first_name' => 'Inline', 'last_name' => 'One',
            'email' => 'inline.one@queue.example',
            'employee_id' => 'INL-001', 'status' => 'active', 'base_salary' => 3000,
        ]);
        Employee::create([
            'first_name' => 'Inline', 'last_name' => 'Two',
            'email' => 'inline.two@queue.example',
            'employee_id' => 'INL-002', 'status' => 'active', 'base_salary' => 4000,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->postJson("/api/v1/hrm/payroll-periods/{$period->id}/process");

        $response->assertStatus(200);
        // Response is a PayslipResource::collection — assert structure.
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
        $this->assertSame('processed', $period->fresh()->status);

        Queue::assertNotPushed(ProcessPayrollPeriodJob::class,
            'Small tenants must NOT dispatch the queue job.');
    }

    public function test_large_tenant_queues_job_and_flips_to_processing(): void
    {
        Queue::fake();

        $period = PayrollPeriod::create([
            'name' => '2026-08', 'start_date' => '2026-08-01',
            'end_date' => '2026-08-31', 'status' => 'draft',
        ]);

        // Partial-mock the service so we don't need to seed 200 employees
        // to cross the threshold. shouldQueueProcessing() is the only
        // method we override; queueProcessPeriod()/dispatch run real code.
        $partial = Mockery::mock(PayrollService::class, [
            app(\App\Tenants\Modules\IAM\Services\WorkflowStatusService::class),
            app(\App\Tenants\Modules\FMS\Services\AccountingService::class),
            app(\App\Tenants\Modules\HRM\Services\AttendanceService::class),
            app(\App\Tenants\Modules\HRM\Services\OvertimeService::class),
            app(\App\Tenants\Modules\Settings\Services\SettingService::class),
        ])->makePartial();
        $partial->shouldReceive('shouldQueueProcessing')->andReturn(true);
        $this->app->instance(PayrollService::class, $partial);

        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->postJson("/api/v1/hrm/payroll-periods/{$period->id}/process");

        $response->assertStatus(202);
        $response->assertJsonPath('meta.queued', true);
        $this->assertSame('processing', $period->fresh()->status,
            'Period status must flip to `processing` when queued.');

        Queue::assertPushed(ProcessPayrollPeriodJob::class, function (ProcessPayrollPeriodJob $job) use ($period) {
            return $job->periodId === $period->id;
        });
    }

    public function test_job_skips_when_period_is_not_in_processing_state(): void
    {
        $period = PayrollPeriod::create([
            'name' => '2026-09', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-30', 'status' => 'draft',
        ]);

        // Job runs synchronously here. Period is still `draft`, not
        // `processing`, so the job must early-exit without throwing.
        $job = new ProcessPayrollPeriodJob($period->id);
        $job->handle(app(PayrollService::class));

        $this->assertSame('draft', $period->fresh()->status,
            'Job must not transition a draft period without going through the controller.');
    }

    public function test_job_processes_period_when_status_is_processing(): void
    {
        Employee::create([
            'first_name' => 'Q', 'last_name' => 'Job',
            'email' => 'q.job@queue.example',
            'employee_id' => 'QJOB-001', 'status' => 'active', 'base_salary' => 2500,
        ]);
        $period = PayrollPeriod::create([
            'name' => '2026-10', 'start_date' => '2026-10-01',
            'end_date' => '2026-10-31', 'status' => 'processing',
        ]);

        $job = new ProcessPayrollPeriodJob($period->id);
        $job->handle(app(PayrollService::class));

        $this->assertSame('processed', $period->fresh()->status,
            'Job must transition processing -> processed on success.');
    }
}
