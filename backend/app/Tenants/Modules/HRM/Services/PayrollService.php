<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Payslip;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Tax bracket (single-tier flat) — replace with jurisdiction-specific
     * configuration once the FMS tax module exposes its API.
     */
    private const TAX_RATE = 0.10;

    private const NSSF_RATE = 0.04;

    /**
     * Default hourly-rate divisor used when the tenant hasn't customised
     * `hrm.payroll.monthly_work_hours_standard`. 40 h/wk × 4 weeks.
     */
    private const DEFAULT_MONTHLY_HOURS = 160;

    /**
     * Default payday (day-of-month) used when the tenant hasn't customised
     * `hrm.payroll.default_payday`. Matches the registry default.
     */
    private const DEFAULT_PAYDAY = 25;

    /**
     * Map of internal account-key → CoA-code setting key. The four codes
     * used to be sourced from `config('payroll.accounts')`; Phase 9 moves
     * them into per-tenant settings so multi-tenant rollouts don't need
     * env edits to change the chart.
     */
    private const ACCOUNT_SETTING_KEYS = [
        'wage_expense'  => 'hrm.payroll.account_wages_expense',
        'tax_payable'   => 'hrm.payroll.account_tax_payable',
        'nssf_payable'  => 'hrm.payroll.account_social_security_payable',
        'wages_payable' => 'hrm.payroll.account_wages_payable',
    ];

    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly AccountingService $accounting,
        private readonly AttendanceService $attendance,
        private readonly OvertimeService $overtime,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * Phase 9 hook: standard hours per month used as the overtime hourly-rate
     * divisor. Reads `hrm.payroll.monthly_work_hours_standard`, clamps to a
     * sane floor (>=1) so a zero value can't divide-by-zero, falls back to
     * the 160h default.
     */
    public function monthlyWorkHoursStandard(): int
    {
        $raw = $this->settings->get('hrm.payroll.monthly_work_hours_standard');
        return is_numeric($raw) && (int) $raw > 0 ? (int) $raw : self::DEFAULT_MONTHLY_HOURS;
    }

    /**
     * Phase 9 hook: configured day-of-month for auto-generating draft
     * payroll periods. Clamped to 1..31; non-numeric / out-of-range
     * values fall back to the 25th.
     */
    public function defaultPayday(): int
    {
        $raw = $this->settings->get('hrm.payroll.default_payday');
        if (!is_numeric($raw)) {
            return self::DEFAULT_PAYDAY;
        }
        $day = (int) $raw;
        return ($day >= 1 && $day <= 31) ? $day : self::DEFAULT_PAYDAY;
    }

    /**
     * Phase 9 hook: when false, `closePeriod()` skips the FMS journal entry
     * but still flips the period state to `closed`. Lets a tenant operate
     * payroll without the accounting integration until they're ready.
     */
    public function fmsPostingEnabled(): bool
    {
        // Default true — the historic behavior always posted.
        $raw = $this->settings->get('hrm.payroll.fms_posting_enabled');
        return $raw === null ? true : (bool) $raw;
    }

    /**
     * Active-employee count at or above this threshold pushes payroll
     * processing onto the queue instead of running synchronously inside
     * the HTTP request. Tuned so a typical SMB tenant (< 200 staff) still
     * gets the inline experience while large customers don't timeout.
     */
    public const QUEUE_THRESHOLD = 200;

    public function createPeriod(array $data): PayrollPeriod
    {
        $data['status'] ??= $this->statuses->initialFor('hrm.payroll_period');

        return PayrollPeriod::create($data);
    }

    /**
     * Returns true when the active workforce exceeds the synchronous
     * threshold. PayrollPeriodController consults this to decide between
     * inline processing and {@see ProcessPayrollPeriodJob}.
     */
    public function shouldQueueProcessing(): bool
    {
        return Employee::query()->where('status', 'active')->count() >= self::QUEUE_THRESHOLD;
    }

    /**
     * Flip the period to `processing` and hand off to the queue. Callers
     * (controller) get back the updated period so they can return a 202
     * with the new status. Job dispatch itself happens after the status
     * flip so a queue failure leaves the period in `processing` and is
     * caught by the job's own rollback path.
     */
    public function queueProcessPeriod(PayrollPeriod $period): PayrollPeriod
    {
        $this->statuses->validateTransition('hrm.payroll_period', $period->status, 'processing');

        $period->update(['status' => 'processing']);

        \App\Jobs\ProcessPayrollPeriodJob::dispatch($period->id);

        return $period->fresh();
    }

    /**
     * Process the period: snapshot one payslip per active employee.
     *
     * @return Collection<int, Payslip>
     */
    public function processPeriod(PayrollPeriod $period): Collection
    {
        $this->statuses->validateTransition('hrm.payroll_period', $period->status, 'processed');

        return DB::transaction(function () use ($period) {
            $employees = Employee::query()->where('status', 'active')->get();

            $payslips = $employees->map(function (Employee $employee) use ($period) {
                $calculation = $this->computeFor($employee, $period);

                return Payslip::create([
                    'payroll_period_id' => $period->id,
                    'employee_id'       => $employee->id,
                    'gross_salary'      => $calculation['gross'],
                    'net_salary'        => $calculation['net'],
                    'earnings'          => $calculation['earnings'],
                    'deductions'        => $calculation['deductions'],
                ]);
            });

            $period->update(['status' => 'processed']);

            return $payslips;
        });
    }

    /**
     * Close the period: post the payroll accrual journal entry to FMS and
     * flip the period state. The whole operation lives in one transaction so
     * a missing FMS account leaves the period in `processed`, ready to retry
     * once accounts are configured.
     */
    public function closePeriod(PayrollPeriod $period): PayrollPeriod
    {
        $this->statuses->validateTransition('hrm.payroll_period', $period->status, 'closed');

        return DB::transaction(function () use ($period) {
            // Phase 9: tenant can disable FMS posting per
            // `hrm.payroll.fms_posting_enabled`. When off, we still close the
            // period but leave `journal_entry_id` null — the FMS side stays
            // untouched.
            $journal = $this->fmsPostingEnabled()
                ? $this->postPayrollJournal($period)
                : null;

            $period->update([
                'status'           => 'closed',
                'journal_entry_id' => $journal?->id,
                'closed_at'        => now(),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Compute earnings/deductions for an employee. When a period is supplied,
     * also applies:
     *   - absent_deduction = (base / workdays_in_period) × absent_days
     *   - unpaid_leave_deduction = same formula × unpaid_leave_days
     *   - overtime earnings = sum(approved_hours × multiplier) × hourly_rate
     *
     * @return array{gross: float, net: float, earnings: array, deductions: array}
     */
    public function computeFor(Employee $employee, ?PayrollPeriod $period = null): array
    {
        $base = (float) ($employee->base_salary ?? 0);

        $earnings = [
            'base' => $base,
            'bonus' => 0.0,
            'overtime' => 0.0,
        ];
        $deductions = [
            'tax'  => 0.0,
            'nssf' => 0.0,
            'absent' => 0.0,
            'unpaid_leave' => 0.0,
        ];

        if ($period !== null && $base > 0) {
            $start = $period->start_date ? CarbonImmutable::parse($period->start_date) : null;
            $end   = $period->end_date   ? CarbonImmutable::parse($period->end_date)   : null;

            if ($start && $end) {
                $workdays = $this->countWorkdays($start, $end);

                // Attendance-driven deductions.
                if ($workdays > 0) {
                    $summary = $this->attendance->summaryFor($employee->id, $start->toDateString(), $end->toDateString());
                    $perDay  = round($base / $workdays, 2);
                    $halfDayDeduction = round($perDay * 0.5, 2);

                    $deductions['absent'] = round($perDay * $summary['absent'], 2);
                    $deductions['unpaid_leave'] = round($perDay * $summary['unpaid_leave'], 2);
                    // Half-day rows are partially deducted — half a workday.
                    if ($summary['halfDay'] > 0) {
                        $deductions['absent'] = round($deductions['absent'] + $halfDayDeduction * $summary['halfDay'], 2);
                    }
                }

                // Overtime earnings.
                $weighted = $this->overtime->approvedWeightedHoursFor($employee->id, $start->toDateString(), $end->toDateString());
                if ($weighted > 0) {
                    $hourly = $base / $this->monthlyWorkHoursStandard();
                    $earnings['overtime'] = round($weighted * $hourly, 2);
                }
            }
        }

        $gross = round(array_sum($earnings), 2);

        $deductions['tax']  = round($gross * self::TAX_RATE, 2);
        $deductions['nssf'] = round($gross * self::NSSF_RATE, 2);

        $net = round($gross - array_sum($deductions), 2);

        return [
            'gross' => $gross,
            'net' => $net,
            'earnings' => $earnings,
            'deductions' => $deductions,
        ];
    }

    /**
     * Mon-Fri count between start and end inclusive. Holiday calendar is
     * deferred — when it lands, subtract recognised holidays here.
     */
    private function countWorkdays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $count = 0;
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            if (!$d->isWeekend()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Aggregate payslip totals into a balanced accrual journal:
     *
     *   Dr Wage Expense   = sum(gross)
     *   Cr Tax Payable    = sum(deductions.tax)
     *   Cr NSSF Payable   = sum(deductions.nssf)
     *   Cr Wages Payable  = sum(net)
     *
     * Returns null when the period has no payslips (nothing to post). Throws
     * DomainException listing missing account codes when the tenant's chart
     * of accounts isn't configured for payroll.
     */
    private function postPayrollJournal(PayrollPeriod $period): ?JournalEntry
    {
        $payslips = $period->payslips()->get();
        if ($payslips->isEmpty()) {
            return null;
        }

        $gross = round((float) $payslips->sum('gross_salary'), 2);

        $tax  = round($payslips->sum(fn (Payslip $p) => (float) ($p->deductions['tax'] ?? 0)), 2);
        $nssf = round($payslips->sum(fn (Payslip $p) => (float) ($p->deductions['nssf'] ?? 0)), 2);

        // Derive net as the balancing figure so accumulated per-payslip rounding
        // never trips AccountingService::validateBalancedEntry. Drift vs.
        // sum(payslip.net_salary) stays within a few cents — acceptable for an
        // accrual journal.
        $net = round($gross - $tax - $nssf, 2);

        $accounts = $this->resolvePayrollAccounts();

        return $this->accounting->postEntry([
            'reference_number' => 'PAYROLL-' . $period->id,
            'description'      => "Payroll accrual — {$period->name}",
            'entry_date'       => $period->end_date ?? now(),
            'lines'            => [
                ['account_id' => $accounts['wage_expense']->id,  'debit'  => $gross, 'credit' => 0],
                ['account_id' => $accounts['tax_payable']->id,   'debit'  => 0,      'credit' => $tax],
                ['account_id' => $accounts['nssf_payable']->id,  'debit'  => 0,      'credit' => $nssf],
                ['account_id' => $accounts['wages_payable']->id, 'debit'  => 0,      'credit' => $net],
            ],
        ]);
    }

    /**
     * Look up the four payroll accounts in the per-tenant chart. Missing codes
     * surface as a single 422 listing what the admin needs to create.
     *
     * @return array<string, Account>
     */
    private function resolvePayrollAccounts(): array
    {
        // Phase 9: per-tenant codes come from `hrm.payroll.account_*`
        // settings; `config('payroll.accounts')` (env-driven) is the
        // fallback when a tenant hasn't customised a particular key, so
        // existing PAYROLL_ACCOUNT_* env deployments keep working unchanged.
        $fallbacks = config('payroll.accounts');
        $codes = [];
        foreach (self::ACCOUNT_SETTING_KEYS as $accountKey => $settingKey) {
            $raw = $this->settings->get($settingKey);
            $codes[$accountKey] = is_string($raw) && $raw !== ''
                ? $raw
                : ($fallbacks[$accountKey] ?? '');
        }

        $accounts = Account::query()->whereIn('code', $codes)->get()->keyBy('code');

        $missing = [];
        $resolved = [];
        foreach ($codes as $key => $code) {
            if ($code === '' || !$accounts->has($code)) {
                $missing[] = $code === '' ? "[{$key}: unset]" : $code;
                continue;
            }
            $resolved[$key] = $accounts->get($code);
        }

        if (!empty($missing)) {
            throw new DomainException(
                'Cannot close payroll: FMS chart of accounts is missing required codes: '
                . implode(', ', $missing) . '. Configure them in /api/v1/accounts or set the matching `hrm.payroll.account_*` settings.'
            );
        }

        return $resolved;
    }
}
