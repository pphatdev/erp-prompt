<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\CashAdvance;
use App\Models\Tenant\Employee;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CashAdvanceService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return CashAdvance::query()
            ->with(['employee', 'bankAccount.glAccount', 'receivableAccount'])
            ->orderByDesc('issued_on')
            ->orderByDesc('created_at');
    }

    /**
     * Issue a cash advance: posts DR Receivable / CR Cash and opens the
     * advance for future settlements. No draft state — issuing IS the post.
     */
    public function issue(array $data): CashAdvance
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before issuing advances.'
            );
        }

        $receivable = Account::query()->findOrFail($data['receivable_account_id']);
        if ($receivable->type !== 'asset') {
            throw new DomainException(
                "Receivable account '{$receivable->code} · {$receivable->name}' must be type 'asset' (got '{$receivable->type}'). " .
                "Typically this is 'Employee Advances Receivable' or similar."
            );
        }

        $employee = Employee::query()->findOrFail($data['employee_id']);

        return DB::transaction(function () use ($data, $bank, $receivable, $employee) {
            $advance = CashAdvance::create([
                'advance_number'        => $data['advance_number'],
                'employee_id'           => $employee->id,
                'bank_account_id'       => $bank->id,
                'receivable_account_id' => $receivable->id,
                'issued_on'             => $data['issued_on'],
                'amount'                => $data['amount'],
                'settled_amount'        => 0,
                'currency'              => $data['currency'] ?? $bank->currency ?? 'USD',
                'payment_method'        => $data['payment_method'] ?? null,
                'reference_number'      => $data['reference_number'] ?? null,
                'purpose'               => $data['purpose'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'status'                => CashAdvance::STATUS_OPEN,
            ]);

            $employeeLabel = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($employee->employee_id ?? 'employee');
            $journal = $this->accounting->postEntry([
                'reference_number' => "CASHADV-{$data['advance_number']}",
                'description'      => "Cash advance {$data['advance_number']} to {$employeeLabel}",
                'entry_date'       => $data['issued_on'],
                'lines'            => [
                    [
                        'account_id' => $receivable->id,
                        'debit'      => (float) $data['amount'],
                        'credit'     => 0.0,
                    ],
                    [
                        'account_id' => $bank->account_id,
                        'debit'      => 0.0,
                        'credit'     => (float) $data['amount'],
                    ],
                ],
            ]);

            $advance->forceFill(['journal_entry_id' => $journal->id])->save();

            return $advance->fresh(['employee', 'bankAccount.glAccount', 'receivableAccount', 'journalEntry']);
        });
    }

    /**
     * Cancel an open advance with no settlements yet. Posts a reversing JE
     * via AccountingService::reverseEntry and flips status to cancelled.
     * Refuses when any settlement amount has already been applied —
     * the settlements must be reversed first.
     */
    public function cancel(CashAdvance $advance): CashAdvance
    {
        if (!$advance->isCancellable()) {
            if ((float) $advance->settled_amount > 0.001) {
                throw new DomainException(
                    "Cash advance {$advance->advance_number} has settlements applied ({$advance->settled_amount}). " .
                    'Reverse the settlement(s) first, then cancel.'
                );
            }
            throw new DomainException("Cash advance {$advance->advance_number} cannot be cancelled (status: {$advance->status}).");
        }

        return DB::transaction(function () use ($advance) {
            if ($advance->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $advance->journalEntry,
                    "CASHADV-{$advance->advance_number}-CANCEL",
                    "Cancellation of cash advance {$advance->advance_number}",
                );
                $advance->reversal_journal_entry_id = $reversal->id;
            }

            $advance->status = CashAdvance::STATUS_CANCELLED;
            $advance->save();

            return $advance->fresh(['employee', 'bankAccount.glAccount', 'receivableAccount', 'journalEntry', 'reversalJournalEntry']);
        });
    }
}
