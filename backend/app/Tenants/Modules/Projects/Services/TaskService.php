<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Services;

use App\Models\Tenant\Leave;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Task;
use App\Models\Tenant\Timesheet;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Maximum hours an employee can log against any single calendar date,
     * summed across all timesheet rows for that day. Sized so two full
     * 8-hour shifts + 30 minutes' contingency still pass — anything more
     * is almost certainly an input error or a billing-fraud attempt.
     */
    public const MAX_HOURS_PER_DAY = 16.0;

    /**
     * Log time against a task. Validates three invariants before persisting:
     *   1. Daily hour cap (MAX_HOURS_PER_DAY).
     *   2. Approved-leave block — an employee on approved leave for that
     *      date cannot also log billable hours.
     *   3. Payroll-period lock — once a period is `closed`, timesheets
     *      that fall within its range are frozen.
     */
    public function logTime(array $data): Timesheet
    {
        return DB::transaction(function () use ($data) {
            $employeeId = (string) $data['employee_id'];
            $logDate    = CarbonImmutable::parse($data['log_date'])->toDateString();
            $hours      = (float) ($data['hours_worked'] ?? 0);

            $this->assertWithinDailyCap($employeeId, $logDate, $hours);
            $this->assertNotOnApprovedLeave($employeeId, $logDate);
            $this->assertNotInClosedPayrollPeriod($logDate);

            $timesheet = Timesheet::create([
                'task_id'      => $data['task_id'],
                'employee_id'  => $employeeId,
                'log_date'     => $logDate,
                'hours_worked' => $hours,
                'notes'        => $data['notes'] ?? null,
            ]);

            // Best-effort: flip a fresh task into in_progress when its
            // first hours arrive. Stays out of the validation chain so a
            // missing Task row never breaks logging.
            $task = Task::find($data['task_id']);
            if ($task && $task->status === 'todo') {
                $task->update(['status' => 'in_progress']);
            }

            return $timesheet;
        });
    }

    /**
     * Update task status (e.g., from Kanban board).
     */
    public function updateStatus(Task $task, string $status): Task
    {
        $task->update(['status' => $status]);
        return $task;
    }

    private function assertWithinDailyCap(string $employeeId, string $logDate, float $hours): void
    {
        if ($hours <= 0) {
            throw new DomainException('hours_worked must be greater than zero.');
        }

        $existing = (float) Timesheet::query()
            ->where('employee_id', $employeeId)
            ->whereDate('log_date', $logDate)
            ->sum('hours_worked');

        if ($existing + $hours > self::MAX_HOURS_PER_DAY) {
            $remaining = max(0, self::MAX_HOURS_PER_DAY - $existing);
            throw new DomainException(sprintf(
                'Daily hour cap exceeded - %.2fh already logged on %s; this entry would push to %.2fh (cap %.0fh). Remaining: %.2fh.',
                $existing,
                $logDate,
                $existing + $hours,
                self::MAX_HOURS_PER_DAY,
                $remaining,
            ));
        }
    }

    private function assertNotOnApprovedLeave(string $employeeId, string $logDate): void
    {
        $blocked = Leave::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $logDate)
            ->whereDate('end_date', '>=', $logDate)
            ->exists();

        if ($blocked) {
            throw new DomainException(sprintf(
                'Cannot log hours on %s - employee has approved leave on that date.',
                $logDate,
            ));
        }
    }

    private function assertNotInClosedPayrollPeriod(string $logDate): void
    {
        $locked = PayrollPeriod::query()
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $logDate)
            ->whereDate('end_date', '>=', $logDate)
            ->exists();

        if ($locked) {
            throw new DomainException(sprintf(
                'Cannot log hours on %s - that date falls inside a closed payroll period.',
                $logDate,
            ));
        }
    }
}
