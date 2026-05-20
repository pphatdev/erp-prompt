<?php

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LedgerEntry;
use App\Models\Tenant\Account;
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

            $journal = JournalEntry::create([
                'reference_number' => $data['reference_number'],
                'description' => $data['description'] ?? null,
                'entry_date' => $data['entry_date'] ?? now(),
                'status' => 'posted',
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
