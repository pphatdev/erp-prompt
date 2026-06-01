<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\CreditNote;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Customer credit notes (AR adjustment).
 *
 * On issue():  DR Sales Returns (or other contra-revenue / expense)
 *              CR Accounts Receivable
 *
 *              When linked to an invoice, the credit amount is rolled into
 *              invoice.paid_amount alongside any receipts so a confirmed
 *              invoice can be closed by credit notes alone.
 *
 * On cancel(): reverses the JE via AccountingService::reverseEntry, decrements
 *              the linked invoice's paid_amount, and demotes paid -> confirmed
 *              when no longer fully covered.
 *
 * Permitted Sales Returns account types: revenue (the GAAP-standard "Sales
 * Returns and Allowances" contra-revenue) OR expense (some tenants book it as
 * a discount/expense). Other types are rejected.
 */
class CreditNoteService
{
    private const ALLOWED_DR_TYPES = ['revenue', 'expense'];

    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return CreditNote::query()
            ->with(['customer', 'invoice', 'salesReturnsAccount', 'arAccount'])
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');
    }

    /**
     * Issue a credit note. Posts the JE immediately — no draft state.
     *
     * Validation:
     *   - Customer must exist.
     *   - AR account must be type 'asset'.
     *   - Sales Returns account must be type 'revenue' or 'expense'.
     *   - Amount must be > 0.
     *   - If invoice_id is provided:
     *     - Invoice must belong to the same customer.
     *     - Invoice must be in 'confirmed' status (no point crediting a new
     *       invoice that hasn't been posted, and you can't credit a cancelled one).
     *     - Amount must be <= invoice.outstanding (0.001 tolerance) — partial
     *       credits are fine, but you can't credit more than the customer owes.
     */
    public function issue(array $data): CreditNote
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);

        $arAccount = Account::query()->findOrFail($data['ar_account_id']);
        if ($arAccount->type !== 'asset') {
            throw new DomainException(
                "AR account '{$arAccount->code} · {$arAccount->name}' must be type 'asset' (got '{$arAccount->type}')."
            );
        }

        $drAccount = Account::query()->findOrFail($data['sales_returns_account_id']);
        if (!in_array($drAccount->type, self::ALLOWED_DR_TYPES, true)) {
            throw new DomainException(
                "Sales Returns account '{$drAccount->code} · {$drAccount->name}' must be type 'revenue' (contra-revenue) or 'expense' (got '{$drAccount->type}')."
            );
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new DomainException('Credit note amount must be greater than zero.');
        }

        $invoice = null;
        if (!empty($data['invoice_id'])) {
            $invoice = Invoice::query()->findOrFail($data['invoice_id']);
            if ($invoice->customer_id !== $customer->id) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} belongs to a different customer."
                );
            }
            if ($invoice->status !== Invoice::STATUS_CONFIRMED) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} is in status '{$invoice->status}' and cannot accept a credit note. " .
                    'Only confirmed invoices with outstanding balance can be credited.'
                );
            }
            $outstanding = $this->outstandingOf($invoice);
            if ($amount > $outstanding + 0.001) {
                throw new DomainException(
                    "Credit amount ({$amount}) exceeds invoice outstanding ({$outstanding}) on {$invoice->invoice_number}."
                );
            }
        }

        return DB::transaction(function () use ($data, $customer, $arAccount, $drAccount, $invoice, $amount) {
            $note = CreditNote::create([
                'credit_note_number'       => $data['credit_note_number'],
                'customer_id'              => $customer->id,
                'invoice_id'               => $invoice?->id,
                'sales_returns_account_id' => $drAccount->id,
                'ar_account_id'            => $arAccount->id,
                'issue_date'               => $data['issue_date'],
                'amount'                   => $amount,
                'currency'                 => $data['currency'] ?? ($invoice ? null : null) ?? 'USD',
                'reason'                   => $data['reason'],
                'notes'                    => $data['notes'] ?? null,
                'status'                   => CreditNote::STATUS_ISSUED,
            ]);

            $invoiceLabel = $invoice ? " against invoice {$invoice->invoice_number}" : '';
            $journal = $this->accounting->postEntry([
                'reference_number' => "CN-{$data['credit_note_number']}",
                'description'      => "Credit note {$data['credit_note_number']} for {$customer->name}{$invoiceLabel}",
                'entry_date'       => $data['issue_date'],
                'lines'            => [
                    [
                        'account_id' => $drAccount->id,
                        'debit'      => $amount,
                        'credit'     => 0.0,
                    ],
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => 0.0,
                        'credit'     => $amount,
                    ],
                ],
            ]);

            $note->forceFill(['journal_entry_id' => $journal->id])->save();

            if ($invoice) {
                $this->applyCreditToInvoice($invoice, $amount);
            }

            return $note->fresh(['customer', 'invoice', 'salesReturnsAccount', 'arAccount', 'journalEntry']);
        });
    }

    /**
     * Cancel an issued credit note: reverses the JE, decrements the linked
     * invoice's paid_amount, and demotes paid -> confirmed if no longer
     * fully covered.
     */
    public function cancel(CreditNote $note): CreditNote
    {
        if (!$note->isCancellable()) {
            throw new DomainException("Credit note {$note->credit_note_number} cannot be cancelled (status: {$note->status}).");
        }

        return DB::transaction(function () use ($note) {
            $note->load(['invoice', 'journalEntry']);

            if ($note->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $note->journalEntry,
                    "CN-{$note->credit_note_number}-CANCEL",
                    "Cancellation of credit note {$note->credit_note_number}",
                );
                $note->reversal_journal_entry_id = $reversal->id;
            }

            if ($note->invoice) {
                $this->unapplyCreditFromInvoice($note->invoice, (float) $note->amount);
            }

            $note->status = CreditNote::STATUS_CANCELLED;
            $note->save();

            return $note->fresh(['customer', 'invoice', 'salesReturnsAccount', 'arAccount', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    // ----- Helpers ----------------------------------------------------------

    private function applyCreditToInvoice(Invoice $invoice, float $amount): void
    {
        $newPaid = round((float) $invoice->paid_amount + $amount, 2);
        $total   = round((float) $invoice->total_amount, 2);
        $status  = $newPaid + 0.001 >= $total
            ? Invoice::STATUS_PAID
            : Invoice::STATUS_CONFIRMED;

        $invoice->forceFill([
            'paid_amount' => $newPaid,
            'status'      => $status,
        ])->save();
    }

    private function unapplyCreditFromInvoice(Invoice $invoice, float $amount): void
    {
        $newPaid = max(0, round((float) $invoice->paid_amount - $amount, 2));

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            $invoice->forceFill(['paid_amount' => $newPaid])->save();
            return;
        }

        $total = round((float) $invoice->total_amount, 2);
        $status = $newPaid + 0.001 >= $total
            ? Invoice::STATUS_PAID
            : Invoice::STATUS_CONFIRMED;

        $invoice->forceFill([
            'paid_amount' => $newPaid,
            'status'      => $status,
        ])->save();
    }

    private function outstandingOf(Invoice $invoice): float
    {
        return round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
    }
}
