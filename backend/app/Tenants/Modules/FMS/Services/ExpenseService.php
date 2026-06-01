<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseLine;
use App\Models\Tenant\Supplier;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return Expense::query()
            ->with(['bankAccount.glAccount', 'supplier', 'lines.account'])
            ->orderByDesc('paid_on')
            ->orderByDesc('created_at');
    }

    /**
     * Record an expense. Posts the journal immediately — no draft.
     * Skips AP entirely (no Bill is created): debits each expense account,
     * credits the bank's GL account once for the total.
     *
     * Invariants:
     *   - Bank must have a linked GL account.
     *   - Each line account must be expense-type.
     *   - sum(lines.amount) must equal `total` (0.001 tolerance).
     *   - Each line must have a positive amount.
     *   - Supplier (if provided) used for traceability only; not validated as vendor.
     */
    public function record(array $data): Expense
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before posting expenses.'
            );
        }

        $supplier = null;
        if (!empty($data['supplier_id'])) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
        }

        $lines = $this->normalizeLines($data['lines'] ?? []);
        $this->assertLineAccountsAreExpense($lines);
        $this->assertSumMatchesTotal($lines, (float) $data['total']);

        return DB::transaction(function () use ($data, $bank, $supplier, $lines) {
            $expense = Expense::create([
                'expense_number'   => $data['expense_number'],
                'bank_account_id'  => $bank->id,
                'supplier_id'      => $supplier?->id,
                'paid_on'          => $data['paid_on'],
                'total'            => $data['total'],
                'currency'         => $data['currency'] ?? $bank->currency ?? 'USD',
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'status'           => Expense::STATUS_POSTED,
            ]);

            $journalLines = [];
            $total = 0.0;
            foreach ($lines as $line) {
                ExpenseLine::create([
                    'expense_id'         => $expense->id,
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

            $payeeLabel = $supplier?->name ?? 'misc';
            $journal = $this->accounting->postEntry([
                'reference_number' => "EXP-{$data['expense_number']}",
                'description'      => "Expense {$data['expense_number']} ({$payeeLabel})",
                'entry_date'       => $data['paid_on'],
                'lines'            => $journalLines,
            ]);

            $expense->forceFill(['journal_entry_id' => $journal->id])->save();

            return $expense->fresh(['bankAccount.glAccount', 'supplier', 'lines.account', 'journalEntry']);
        });
    }

    /**
     * Cancel a posted expense. Reverses the JE via
     * AccountingService::reverseEntry so audit history stays intact.
     */
    public function cancel(Expense $expense): Expense
    {
        if (!$expense->isCancellable()) {
            throw new DomainException(
                "Expense {$expense->expense_number} cannot be cancelled (status: {$expense->status})."
            );
        }

        return DB::transaction(function () use ($expense) {
            if ($expense->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $expense->journalEntry,
                    "EXP-{$expense->expense_number}-CANCEL",
                    "Cancellation of expense {$expense->expense_number}",
                );
                $expense->reversal_journal_entry_id = $reversal->id;
            }

            $expense->status = Expense::STATUS_CANCELLED;
            $expense->save();

            return $expense->fresh(['bankAccount.glAccount', 'supplier', 'lines.account', 'journalEntry', 'reversalJournalEntry']);
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
                throw new DomainException('Each expense line must have a positive amount.');
            }
            $out[] = [
                'account_id'         => $line['account_id'],
                'description'        => $line['description'] ?? null,
                'amount'             => $amt,
                'receipt_attachment' => $line['receipt_attachment'] ?? null,
            ];
        }
        if (empty($out)) {
            throw new DomainException('An expense must have at least one valid line.');
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
                    "Account '{$a->code} · {$a->name}' must be type 'expense' for expense lines (got '{$a->type}')."
                );
            }
        }
    }

    private function assertSumMatchesTotal(array $lines, float $total): void
    {
        $sum = round(array_sum(array_column($lines, 'amount')), 2);
        if (abs($sum - round($total, 2)) > 0.001) {
            throw new DomainException(
                "Sum of line amounts ({$sum}) does not equal expense total ({$total})."
            );
        }
    }
}
