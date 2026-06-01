<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\CashAdvance;
use App\Models\Tenant\CashAdvanceSettlement;
use App\Models\Tenant\CashAdvanceSettlementLine;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CashAdvanceSettlementService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return CashAdvanceSettlement::query()
            ->with([
                'cashAdvance.employee',
                'cashAdvance.receivableAccount',
                'bankAccount.glAccount',
                'lines.account',
            ])
            ->orderByDesc('settled_on')
            ->orderByDesc('created_at');
    }

    /**
     * Record a settlement against an open cash advance.
     *
     * Invariants:
     *   - Advance must be in OPEN_STATUSES (open or partially_settled).
     *   - actual_amount = sum(lines.amount) (0.001 tolerance).
     *   - applied = actual_amount - unused_returned > 0.
     *   - applied ≤ advance.outstandingAmount.
     *   - Each line account must be expense-type.
     *   - If unused_returned > 0, bank_account_id is required + must have GL link.
     *
     * Posts via AccountingService::postEntry:
     *   DR Expense (per line)
     *   DR Cash    (= unused_returned, if any)
     *   CR Employee Advances Receivable (= applied)
     *
     * Then rolls advance.settled_amount += applied and promotes status
     * (open → partially_settled → closed) atomically.
     */
    public function record(array $data): CashAdvanceSettlement
    {
        $advance = CashAdvance::query()
            ->with('receivableAccount')
            ->findOrFail($data['cash_advance_id']);

        if (!in_array($advance->status, CashAdvance::OPEN_STATUSES, true)) {
            throw new DomainException(
                "Cash advance {$advance->advance_number} is not open for settlement (status: {$advance->status})."
            );
        }
        if (!$advance->receivableAccount) {
            throw new DomainException(
                "Cash advance {$advance->advance_number} has no receivable account linked."
            );
        }

        $actual         = round((float) $data['actual_amount'], 2);
        $unusedReturned = round((float) ($data['unused_returned'] ?? 0), 2);
        $applied        = round($actual - $unusedReturned, 2);

        if ($actual <= 0) {
            throw new DomainException('Actual amount must be greater than zero.');
        }
        if ($unusedReturned < 0) {
            throw new DomainException('Unused returned cannot be negative.');
        }
        if ($applied <= 0) {
            throw new DomainException(
                "Applied amount ({$applied}) must be positive — unused returned ({$unusedReturned}) cannot meet or exceed actual ({$actual})."
            );
        }
        $outstanding = $advance->outstandingAmount();
        if ($applied > $outstanding + 0.001) {
            throw new DomainException(
                "Applied amount ({$applied}) exceeds advance outstanding ({$outstanding})."
            );
        }

        $bank = null;
        if ($unusedReturned > 0.001) {
            if (empty($data['bank_account_id'])) {
                throw new DomainException(
                    'Bank account is required when unused cash is returned.'
                );
            }
            $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
            if (!$bank->account_id || !$bank->glAccount) {
                throw new DomainException(
                    "Bank account '{$bank->name}' has no linked GL account. " .
                    'Link it to a Cash/Bank account in Chart of Accounts before recording settlements with returned cash.'
                );
            }
        } elseif (!empty($data['bank_account_id'])) {
            $bank = BankAccount::query()->findOrFail($data['bank_account_id']);
        }

        $lines = $this->normalizeLines($data['lines'] ?? []);
        $this->assertLineAccountsAreExpense($lines);
        $this->assertLinesSumToActual($lines, $actual);

        return DB::transaction(function () use ($data, $advance, $bank, $lines, $actual, $unusedReturned, $applied) {
            $settlement = CashAdvanceSettlement::create([
                'settlement_number'  => $data['settlement_number'],
                'cash_advance_id'    => $advance->id,
                'bank_account_id'    => $bank?->id,
                'settled_on'         => $data['settled_on'],
                'actual_amount'      => $actual,
                'unused_returned'    => $unusedReturned,
                'payment_method'     => $data['payment_method'] ?? null,
                'reference_number'   => $data['reference_number'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'status'             => CashAdvanceSettlement::STATUS_POSTED,
            ]);

            $journalLines = [];
            foreach ($lines as $line) {
                CashAdvanceSettlementLine::create([
                    'settlement_id'      => $settlement->id,
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
            }
            if ($unusedReturned > 0.001 && $bank) {
                $journalLines[] = [
                    'account_id' => $bank->account_id,
                    'debit'      => $unusedReturned,
                    'credit'     => 0.0,
                ];
            }
            $journalLines[] = [
                'account_id' => $advance->receivable_account_id,
                'debit'      => 0.0,
                'credit'     => $actual,
            ];

            $journal = $this->accounting->postEntry([
                'reference_number' => "CASHADV-SETTLE-{$data['settlement_number']}",
                'description'      => "Settlement {$data['settlement_number']} of cash advance {$advance->advance_number}",
                'entry_date'       => $data['settled_on'],
                'lines'            => $journalLines,
            ]);

            $settlement->forceFill(['journal_entry_id' => $journal->id])->save();

            $this->rollAdvanceForward($advance, $applied);

            return $settlement->fresh([
                'cashAdvance.employee',
                'cashAdvance.receivableAccount',
                'bankAccount.glAccount',
                'lines.account',
                'journalEntry',
            ]);
        });
    }

    /**
     * Cancel a posted settlement. Reverses its journal via
     * AccountingService::reverseEntry, decrements the advance's
     * settled_amount, and downgrades the advance's status if needed.
     *
     * If the advance was previously closed and is now back below its
     * issued amount, it rolls back to partially_settled. If settled_amount
     * drops to zero, it rolls back to open.
     */
    public function cancel(CashAdvanceSettlement $settlement): CashAdvanceSettlement
    {
        if (!$settlement->isCancellable()) {
            throw new DomainException(
                "Settlement {$settlement->settlement_number} cannot be cancelled (status: {$settlement->status})."
            );
        }

        $advance = $settlement->cashAdvance;
        if (!$advance) {
            throw new DomainException(
                "Settlement {$settlement->settlement_number} has no linked cash advance."
            );
        }

        return DB::transaction(function () use ($settlement, $advance) {
            if ($settlement->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $settlement->journalEntry,
                    "CASHADV-SETTLE-{$settlement->settlement_number}-CANCEL",
                    "Cancellation of settlement {$settlement->settlement_number}",
                );
                $settlement->reversal_journal_entry_id = $reversal->id;
            }

            $settlement->status = CashAdvanceSettlement::STATUS_CANCELLED;
            $settlement->save();

            $this->rollAdvanceBack($advance, $settlement->appliedToAdvance());

            return $settlement->fresh([
                'cashAdvance.employee',
                'cashAdvance.receivableAccount',
                'bankAccount.glAccount',
                'lines.account',
                'journalEntry',
                'reversalJournalEntry',
            ]);
        });
    }

    // ----- Helpers ----------------------------------------------------------

    private function rollAdvanceForward(CashAdvance $advance, float $applied): void
    {
        $locked = CashAdvance::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
        $newSettled = round((float) $locked->settled_amount + $applied, 2);
        $locked->settled_amount = $newSettled;

        if ($newSettled + 0.001 >= (float) $locked->amount) {
            $locked->status = CashAdvance::STATUS_CLOSED;
        } else {
            $locked->status = CashAdvance::STATUS_PARTIALLY_SETTLED;
        }
        $locked->save();
    }

    private function rollAdvanceBack(CashAdvance $advance, float $applied): void
    {
        $locked = CashAdvance::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
        $newSettled = round((float) $locked->settled_amount - $applied, 2);
        if ($newSettled < 0) $newSettled = 0.0;
        $locked->settled_amount = $newSettled;

        if ($newSettled <= 0.001) {
            $locked->status = CashAdvance::STATUS_OPEN;
        } elseif ($newSettled + 0.001 >= (float) $locked->amount) {
            $locked->status = CashAdvance::STATUS_CLOSED;
        } else {
            $locked->status = CashAdvance::STATUS_PARTIALLY_SETTLED;
        }
        $locked->save();
    }

    private function normalizeLines(array $raw): array
    {
        $out = [];
        foreach ($raw as $line) {
            if (empty($line['account_id'])) continue;
            $amt = round((float) ($line['amount'] ?? 0), 2);
            if ($amt <= 0) {
                throw new DomainException('Each settlement line must have a positive amount.');
            }
            $out[] = [
                'account_id'         => $line['account_id'],
                'description'        => $line['description'] ?? null,
                'amount'             => $amt,
                'receipt_attachment' => $line['receipt_attachment'] ?? null,
            ];
        }
        if (empty($out)) {
            throw new DomainException('A settlement must have at least one valid line.');
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
                    "Account '{$a->code} · {$a->name}' must be type 'expense' for settlement lines (got '{$a->type}')."
                );
            }
        }
    }

    private function assertLinesSumToActual(array $lines, float $actual): void
    {
        $sum = round(array_sum(array_column($lines, 'amount')), 2);
        if (abs($sum - round($actual, 2)) > 0.001) {
            throw new DomainException(
                "Sum of line amounts ({$sum}) does not equal actual amount ({$actual})."
            );
        }
    }
}
