<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Tenants\Modules\HRM\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily attendance reconciliation. Runs at 01:00 (configured in
 * routes/console.php) over the *previous* day so the day's clock-ins have
 * landed before the gap-fill pass picks up missing rows.
 *
 * Idempotent: AttendanceService::reconcileAll() skips dates that already
 * have a row (clock-in or earlier reconcile). Safe to retry.
 *
 * Multi-tenant: dispatch one job per tenant via `dispatch()->onConnection('tenant-X')`,
 * or call the manual endpoint per-tenant from a scheduled command that
 * iterates all tenants. This class itself is tenant-agnostic — it
 * processes whatever tenant connection is active when it runs.
 */
class ReconcileAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $date = null)
    {
    }

    public function handle(AttendanceService $attendance): void
    {
        // Default to yesterday — the cron fires at 01:00 so the prior
        // calendar day has finished accruing real clock-ins.
        $date = $this->date ?? CarbonImmutable::yesterday()->toDateString();

        $result = $attendance->reconcileAll($date);

        Log::info('Attendance reconciled', [
            'date'      => $date,
            'processed' => $result['processed'],
            'created'   => $result['created'],
            'skipped'   => $result['skipped'],
        ]);
    }
}
