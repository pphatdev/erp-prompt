<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\PayrollPeriod;
use App\Tenants\Modules\HRM\Services\PayrollService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued payroll processing for tenants whose active workforce exceeds
 * the synchronous threshold. PayrollPeriodController dispatches this job
 * when `Employee::active()->count() >= QUEUE_THRESHOLD`; smaller tenants
 * keep running the synchronous path so the controller still returns the
 * payslip collection in the response.
 *
 * Multi-tenant safety: the `QueueTenancyBootstrapper` (registered in
 * config/tenancy.php) serializes the active tenant on dispatch and
 * re-initializes it inside `handle()` before this job runs. We do NOT
 * call `tenancy()->initialize()` ourselves — that would double-init.
 *
 * Idempotency: re-queues of the same period are tolerated. The job
 * resolves the period by id; if the row is already in a non-`processing`
 * state (someone closed it manually, or another worker won the race) the
 * job logs and exits. `PayrollService::processPeriod` itself wraps the
 * payslip creation in a DB transaction.
 *
 * Failure: on exception the job marks the period back to `draft` so the
 * UI can resubmit. Without that step a failed run would leave the period
 * stuck in `processing` forever.
 */
class ProcessPayrollPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // payroll is not safe to silently retry
    public int $timeout = 600; // 10 minutes — covers ~5k employees

    public function __construct(public readonly string $periodId)
    {
    }

    public function handle(PayrollService $payroll): void
    {
        $period = PayrollPeriod::find($this->periodId);
        if (!$period) {
            Log::warning('ProcessPayrollPeriodJob: period not found', [
                'period_id' => $this->periodId,
            ]);
            return;
        }

        // Only consume work for periods that the controller flipped to
        // `processing`. If a parallel worker / manual sync run already
        // moved the period forward, bail out cleanly.
        if ($period->status !== 'processing') {
            Log::info('ProcessPayrollPeriodJob: period not in processing state, skipping', [
                'period_id' => $period->id,
                'status'    => $period->status,
            ]);
            return;
        }

        try {
            // PayrollService::processPeriod transitions
            //   processing -> processed (seeded via TenantDatabaseSeeder).
            $payslips = $payroll->processPeriod($period);

            Log::info('Payroll period processed via queue', [
                'period_id'      => $period->id,
                'payslip_count'  => $payslips->count(),
            ]);
        } catch (DomainException | Throwable $e) {
            // Roll the period back so it can be reprocessed by the user.
            $period->refresh();
            if ($period->status === 'processing') {
                $period->update(['status' => 'draft']);
            }

            Log::error('ProcessPayrollPeriodJob: payroll processing failed', [
                'period_id' => $period->id,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
