<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DebitNote;
use App\Models\Tenant\Invoice;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Customer debit notes (AR adjustment, opposite direction of CreditNote).
 *
 * On issue():  DR Accounts Receivable
 *              CR Revenue (or selected income account)
 *
 *              When `invoice_id` is set it's used for traceability only —
 *              the linked invoice's paid_amount / status are NOT modified.
 *              The debit note stands as its own AR balance, settled by a
 *              future Receipt. This is the deliberate opposite of CreditNote,
 *              which folds into invoice.paid_amount.
 *
 * On cancel(): reverses the JE via AccountingService::reverseEntry. No
 *              invoice rollback (none was applied).
 */
class DebitNoteService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return DebitNote::query()
            ->with(['customer', 'invoice', 'revenueAccount', 'arAccount'])
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');
    }

    /**
     * Issue a debit note. Posts the JE immediately — no draft state.
     *
     * Validation:
     *   - Customer must exist.
     *   - AR account must be type 'asset'.
     *   - Revenue account must be type 'revenue'.
     *   - Amount must be > 0.
     *   - If invoice_id is provided: invoice must belong to the same customer.
     *     No status or outstanding check — debit notes can attach to any
     *     invoice (including paid / cancelled) for traceability.
     */
    public function issue(array $data): DebitNote
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);

        $arAccount = Account::query()->findOrFail($data['ar_account_id']);
        if ($arAccount->type !== 'asset') {
            throw new DomainException(
                "AR account '{$arAccount->code} · {$arAccount->name}' must be type 'asset' (got '{$arAccount->type}')."
            );
        }

        $revenueAccount = Account::query()->findOrFail($data['revenue_account_id']);
        if ($revenueAccount->type !== 'revenue') {
            throw new DomainException(
                "Revenue account '{$revenueAccount->code} · {$revenueAccount->name}' must be type 'revenue' (got '{$revenueAccount->type}')."
            );
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new DomainException('Debit note amount must be greater than zero.');
        }

        $invoice = null;
        if (!empty($data['invoice_id'])) {
            $invoice = Invoice::query()->findOrFail($data['invoice_id']);
            if ($invoice->customer_id !== $customer->id) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} belongs to a different customer."
                );
            }
        }

        return DB::transaction(function () use ($data, $customer, $arAccount, $revenueAccount, $invoice, $amount) {
            $note = DebitNote::create([
                'debit_note_number'  => $data['debit_note_number'],
                'customer_id'        => $customer->id,
                'invoice_id'         => $invoice?->id,
                'revenue_account_id' => $revenueAccount->id,
                'ar_account_id'      => $arAccount->id,
                'issue_date'         => $data['issue_date'],
                'amount'             => $amount,
                'currency'           => $data['currency'] ?? 'USD',
                'reason'             => $data['reason'],
                'notes'              => $data['notes'] ?? null,
                'status'             => DebitNote::STATUS_ISSUED,
            ]);

            $invoiceLabel = $invoice ? " (re: invoice {$invoice->invoice_number})" : '';
            $journal = $this->accounting->postEntry([
                'reference_number' => "DN-{$data['debit_note_number']}",
                'description'      => "Debit note {$data['debit_note_number']} for {$customer->name}{$invoiceLabel}",
                'entry_date'       => $data['issue_date'],
                'lines'            => [
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => $amount,
                        'credit'     => 0.0,
                    ],
                    [
                        'account_id' => $revenueAccount->id,
                        'debit'      => 0.0,
                        'credit'     => $amount,
                    ],
                ],
            ]);

            $note->forceFill(['journal_entry_id' => $journal->id])->save();

            return $note->fresh(['customer', 'invoice', 'revenueAccount', 'arAccount', 'journalEntry']);
        });
    }

    /**
     * Cancel an issued debit note: reverses the JE. No invoice rollback
     * (debit notes don't fold into invoice.paid_amount).
     */
    public function cancel(DebitNote $note): DebitNote
    {
        if (!$note->isCancellable()) {
            throw new DomainException("Debit note {$note->debit_note_number} cannot be cancelled (status: {$note->status}).");
        }

        return DB::transaction(function () use ($note) {
            $note->load(['journalEntry']);

            if ($note->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $note->journalEntry,
                    "DN-{$note->debit_note_number}-CANCEL",
                    "Cancellation of debit note {$note->debit_note_number}",
                );
                $note->reversal_journal_entry_id = $reversal->id;
            }

            $note->status = DebitNote::STATUS_CANCELLED;
            $note->save();

            return $note->fresh(['customer', 'invoice', 'revenueAccount', 'arAccount', 'journalEntry', 'reversalJournalEntry']);
        });
    }
}
