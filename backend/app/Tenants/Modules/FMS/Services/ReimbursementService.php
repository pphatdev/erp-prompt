<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Reimbursement;
use App\Models\Tenant\ReimbursementLine;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReimbursementService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return Reimbursement::query()
            ->with(['employee', 'bankAccount.glAccount', 'lines.account'])
            ->orderByDesc('paid_on')
            ->orderByDesc('created_at');
    }

    /**
     * Record an employee reimbursement. Posts the journal immediately —
     * no draft state, by design. Skips the AP step entirely (employees
     * aren't tracked as vendors): debits each expense account directly,
     * credits the bank's GL account once for the total.
     *
     * Invariants:
     *   - Bank must have a linked GL account.
     *   - Each line account must be expense-type (catches obvious mistakes
     *     like billing payroll to a liability).
     *   - sum(lines.amount) must equal the header amount (0.001 tolerance).
     *   - Each line must have a positive amount.
     */
    public function record(array $data): Reimbursement
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before posting reimbursements.'
            );
        }

        $employee = Employee::query()->findOrFail($data['employee_id']);

        $lines = $this->normalizeLines($data['lines'] ?? []);
        $this->assertLineAccountsAreExpense($lines);
        $this->assertSumMatchesHeader($lines, (float) $data['amount']);

        return DB::transaction(function () use ($data, $bank, $employee, $lines) {
            $reimb = Reimbursement::create([
                'reimbursement_number' => $data['reimbursement_number'],
                'employee_id'          => $employee->id,
                'bank_account_id'      => $bank->id,
                'paid_on'              => $data['paid_on'],
                'amount'               => $data['amount'],
                'currency'             => $data['currency'] ?? $bank->currency ?? 'USD',
                'payment_method'       => $data['payment_method'] ?? null,
                'reference_number'     => $data['reference_number'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'status'               => Reimbursement::STATUS_POSTED,
            ]);

            $journalLines = [];
            $total = 0.0;
            foreach ($lines as $line) {
                ReimbursementLine::create([
                    'reimbursement_id'   => $reimb->id,
                    'account_id'         => $line['account_id'],
                    'description'        => $line['description'] ?? null,
                    'amount'             => $line['amount'],
                    'receipt_attachment' => $line['receipt_attachment'] ?? null,
                ]);
                $journalLines[] = [
                    'account_id' => $line['account_id'],
                    'debit'      => $line['amount'],
                    'credit'     => 0.0,
                ];
                $total += $line['amount'];
            }
            $journalLines[] = [
                'account_id' => $bank->account_id,
                'debit'      => 0.0,
                'credit'     => round($total, 2),
            ];

            $employeeLabel = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->employee_id ?? 'employee');
            $journal = $this->accounting->postEntry([
                'reference_number' => "REIMB-{$data['reimbursement_number']}",
                'description'      => "Reimbursement {$data['reimbursement_number']} to {$employeeLabel}",
                'entry_date'       => $data['paid_on'],
                'lines'            => $journalLines,
            ]);

            $reimb->forceFill(['journal_entry_id' => $journal->id])->save();

            return $reimb->fresh(['employee', 'bankAccount.glAccount', 'lines.account', 'journalEntry']);
        });
    }

    /**
     * Cancel a posted reimbursement. Reverses the JE via
     * AccountingService::reverseEntry so the audit history stays intact,
     * stores the reversal id, and flips status to cancelled.
     */
    public function cancel(Reimbursement $reimb): Reimbursement
    {
        if (!$reimb->isCancellable()) {
            throw new DomainException(
                "Reimbursement {$reimb->reimbursement_number} cannot be cancelled (status: {$reimb->status})."
            );
        }

        return DB::transaction(function () use ($reimb) {
            if ($reimb->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $reimb->journalEntry,
                    "REIMB-{$reimb->reimbursement_number}-CANCEL",
                    "Cancellation of reimbursement {$reimb->reimbursement_number}",
                );
                $reimb->reversal_journal_entry_id = $reversal->id;
            }

            $reimb->status = Reimbursement::STATUS_CANCELLED;
            $reimb->save();

            return $reimb->fresh(['employee', 'bankAccount.glAccount', 'lines.account', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    // ----- Helpers ----------------------------------------------------------

    private function normalizeLines(array $raw): array
    {
        $out = [];
        foreach ($raw as $line) {
            if (empty($line['account_id'])) continue;
            $amt = round((float) ($line['amount'] ?? 0), 2);
            if ($amt <= 0) {
                throw new DomainException('Each reimbursement line must have a positive amount.');
            }
            $out[] = [
                'account_id'         => $line['account_id'],
                'description'        => $line['description'] ?? null,
                'amount'             => $amt,
                'receipt_attachment' => $line['receipt_attachment'] ?? null,
            ];
        }
        if (empty($out)) {
            throw new DomainException('A reimbursement must have at least one valid line.');
        }
        return $out;
    }

    private function assertLineAccountsAreExpense(array $lines): void
    {
        $ids = collect($lines)->pluck('account_id')->unique()->all();
        $accounts = Account::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($lines as $line) {
            $a = $accounts[$line['account_id']] ?? null;
            if (!$a) {
                throw new DomainException("Account {$line['account_id']} not found.");
            }
            if ($a->type !== 'expense') {
                throw new DomainException(
                    "Account '{$a->code} · {$a->name}' must be type 'expense' for reimbursement lines (got '{$a->type}')."
                );
            }
        }
    }

    private function assertSumMatchesHeader(array $lines, float $headerAmount): void
    {
        $sum = round(array_sum(array_column($lines, 'amount')), 2);
        if (abs($sum - round($headerAmount, 2)) > 0.001) {
            throw new DomainException(
                "Sum of line amounts ({$sum}) does not equal reimbursement amount ({$headerAmount})."
            );
        }
    }
}
