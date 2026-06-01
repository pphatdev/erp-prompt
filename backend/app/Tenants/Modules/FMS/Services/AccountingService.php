<?php

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\FiscalPeriod;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingService
{
    /**
     * Post a balanced journal entry to the ledger.
     */
    public function postEntry(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $this->validateBalancedEntry($data['lines']);
            $this->assertEntryDateNotLocked($data['entry_date'] ?? null);

            $journal = JournalEntry::create([
                'reference_number'     => $data['reference_number'],
                'description'          => $data['description'] ?? null,
                'entry_date'           => $data['entry_date'] ?? now(),
                'status'               => 'posted',
                'reverses_journal_id'  => $data['reverses_journal_id'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                LedgerEntry::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);

                // Update Account Balance
                $this->updateAccountBalance($line['account_id'], $line['debit'] ?? 0, $line['credit'] ?? 0);
            }

            return $journal;
        });
    }

    /**
     * Reverse a posted journal entry by atomically posting an offsetting
     * journal (DR↔CR swapped), then flipping the original to `reversed`.
     *
     * @throws DomainException if the original is not `posted` or has already
     *                          been reversed.
     */
    public function reverseEntry(JournalEntry $original, ?string $reference = null, ?string $memo = null): JournalEntry
    {
        if ($original->status !== 'posted') {
            throw new DomainException("Only posted journals can be reversed (current status: {$original->status}).");
        }
        if ($original->reversed_by_journal_id !== null) {
            throw new DomainException("Journal {$original->reference_number} has already been reversed.");
        }

        return DB::transaction(function () use ($original, $reference, $memo) {
            // Lock the original row so concurrent reverse calls can't race.
            $locked = JournalEntry::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($locked->reversed_by_journal_id !== null) {
                throw new DomainException("Journal {$locked->reference_number} has already been reversed.");
            }

            $lines = $locked->entries()->get()->map(fn (LedgerEntry $e) => [
                'account_id' => $e->account_id,
                'debit'      => (float) $e->credit,
                'credit'     => (float) $e->debit,
            ])->all();

            $reversal = $this->postEntry([
                'reference_number'    => $reference ?? ($locked->reference_number . '-REV'),
                'description'         => $memo ?? ("Reversal of {$locked->reference_number}"),
                'entry_date'          => now()->toDateString(),
                'lines'               => $lines,
                'reverses_journal_id' => $locked->id,
            ]);

            $locked->forceFill([
                'status'                 => 'reversed',
                'reversed_by_journal_id' => $reversal->id,
            ])->save();

            return $reversal;
        });
    }

    /**
     * Refuse to post into a locked fiscal period. This is the single gate
     * that downstream services rely on. PeriodClosingService bypasses by
     * design (it posts the closing JE before flipping the period to locked).
     *
     * Accepts string Y-m-d, DateTime, or null (null falls through to now()).
     */
    protected function assertEntryDateNotLocked(mixed $entryDate): void
    {
        $date = $entryDate;
        if ($date === null) {
            $date = now()->toDateString();
        } elseif ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        } else {
            $date = (string) $date;
            if (strlen($date) > 10) {
                $date = substr($date, 0, 10);
            }
        }

        $locked = FiscalPeriod::query()
            ->where('status', FiscalPeriod::STATUS_LOCKED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date',   '>=', $date)
            ->first();

        if ($locked) {
            throw new DomainException(
                "Cannot post a journal dated {$date}: it falls inside locked period " .
                "{$locked->period_number} ({$locked->name})."
            );
        }
    }

    /**
     * Ensure total Debit equals total Credit.
     */
    protected function validateBalancedEntry(array $lines): void
    {
        $totalDebit = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new Exception("Unbalanced journal entry: Debit ({$totalDebit}) does not equal Credit ({$totalCredit}).");
        }
    }

    /**
     * Update the account balance based on entry type.
     */
    protected function updateAccountBalance(string $accountId, float $debit, float $credit): void
    {
        $account = Account::findOrFail($accountId);
        
        // Simple balance logic (can be more complex based on Account Type)
        $account->balance += ($debit - $credit);
        $account->save();
    }
}
