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

    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly AccountingService $accounting,
    ) {
    }

    public function createPeriod(array $data): PayrollPeriod
    {
        $data['status'] ??= $this->statuses->initialFor('hrm.payroll_period');

        return PayrollPeriod::create($data);
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
                $calculation = $this->computeFor($employee);

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
            $journal = $this->postPayrollJournal($period);

            $period->update([
                'status'           => 'closed',
                'journal_entry_id' => $journal?->id,
                'closed_at'        => now(),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Compute earnings/deductions for an employee. Pure function — no I/O.
     *
     * @return array{gross: float, net: float, earnings: array, deductions: array}
     */
    public function computeFor(Employee $employee): array
    {
        $base = (float) ($employee->base_salary ?? 0);

        $earnings = [
            'base' => $base,
            'bonus' => 0.0,
        ];

        $gross = array_sum($earnings);

        $deductions = [
            'tax'  => round($gross * self::TAX_RATE, 2),
            'nssf' => round($gross * self::NSSF_RATE, 2),
        ];

        $net = round($gross - array_sum($deductions), 2);

        return [
            'gross' => round($gross, 2),
            'net' => $net,
            'earnings' => $earnings,
            'deductions' => $deductions,
        ];
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
        $codes = config('payroll.accounts');
        $accounts = Account::query()->whereIn('code', $codes)->get()->keyBy('code');

        $missing = [];
        $resolved = [];
        foreach ($codes as $key => $code) {
            if (!$accounts->has($code)) {
                $missing[] = $code;
                continue;
            }
            $resolved[$key] = $accounts->get($code);
        }

        if (!empty($missing)) {
            throw new DomainException(
                'Cannot close payroll: FMS chart of accounts is missing required codes: '
                . implode(', ', $missing) . '. Configure them in /api/v1/accounts or override config/payroll.php.'
            );
        }

        return $resolved;
    }
}
