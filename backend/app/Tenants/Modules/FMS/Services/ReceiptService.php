<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\ReceiptInvoiceApplication;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Customer receipt service — AR mirror of BillPaymentService.
 *
 * On record():  DR Bank's GL account (= header amount)
 *               CR AR account     (= one CR line per applied invoice)
 *
 * On cancel():  reverses the JE via AccountingService::reverseEntry
 *               and rolls back each invoice's paid_amount / status.
 *
 * The Invoice status enum has no `partially_paid` step — confirmed invoices
 * stay `confirmed` until the cumulative paid_amount reaches total_amount,
 * at which point they flip to `paid`. Cancellation reverses that flip.
 */
class ReceiptService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return Receipt::query()
            ->with(['customer', 'bankAccount.glAccount', 'arAccount', 'applications.invoice'])
            ->orderByDesc('received_on')
            ->orderByDesc('created_at');
    }

    /**
     * Record a customer receipt that settles one or many invoices for a
     * single customer. Posts a balanced JE the moment it's saved — receipts
     * do not have a draft state.
     *
     * Validation:
     *   - Bank must have a linked GL account (otherwise we can't debit Cash).
     *   - AR account must be type 'asset'.
     *   - Every applied invoice must belong to the receipt's customer.
     *   - Every applied invoice must be confirmed (i.e. already on the books
     *     as AR) and have positive outstanding.
     *   - Each applied_amount must be > 0 and <= invoice.outstanding.
     *   - Sum of applied_amounts must equal the receipt header amount
     *     (within 0.001 tolerance).
     */
    public function record(array $data): Receipt
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before posting receipts.'
            );
        }

        $arAccount = Account::query()->findOrFail($data['ar_account_id']);
        if ($arAccount->type !== 'asset') {
            throw new DomainException(
                "AR account '{$arAccount->code} · {$arAccount->name}' must be type 'asset' (got '{$arAccount->type}'). " .
                "Typically this is the 'Accounts Receivable' account also referenced by `fms.ar_account_code`."
            );
        }

        $applications = $this->normalizeApplications($data['applications'] ?? []);
        $invoiceIds   = collect($applications)->pluck('invoice_id')->all();
        $invoices     = Invoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');

        $this->assertInvoicesBelongToCustomer($invoices, $data['customer_id']);
        $this->assertInvoicesOpen($invoices);
        $this->assertAppliedAmountsFitInvoices($applications, $invoices);
        $this->assertSumMatchesHeader($applications, (float) $data['amount']);

        return DB::transaction(function () use ($data, $bank, $arAccount, $invoices, $applications) {
            $receipt = Receipt::create([
                'receipt_number'   => $data['receipt_number'],
                'customer_id'      => $data['customer_id'],
                'bank_account_id'  => $bank->id,
                'ar_account_id'    => $arAccount->id,
                'received_on'      => $data['received_on'],
                'amount'           => $data['amount'],
                'currency'         => $data['currency'] ?? $bank->currency ?? 'USD',
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'status'           => Receipt::STATUS_POSTED,
            ]);

            // Build the journal lines: one DR to the bank's GL for the total +
            // one CR per applied invoice (against the receipt's AR account).
            $journalLines = [];
            $journalLines[] = [
                'account_id' => $bank->account_id,
                'debit'      => round((float) $data['amount'], 2),
                'credit'     => 0.0,
            ];
            foreach ($applications as $app) {
                $journalLines[] = [
                    'account_id' => $arAccount->id,
                    'debit'      => 0.0,
                    'credit'     => $app['applied_amount'],
                ];
            }

            $journal = $this->accounting->postEntry([
                'reference_number' => "RCPT-{$data['receipt_number']}",
                'description'      => "Receipt {$data['receipt_number']} from " . ($receipt->customer?->name ?? 'customer'),
                'entry_date'       => $data['received_on'],
                'lines'            => $journalLines,
            ]);

            $receipt->forceFill(['journal_entry_id' => $journal->id])->save();

            // Persist applications and roll each invoice's paid_amount + status.
            foreach ($applications as $app) {
                ReceiptInvoiceApplication::create([
                    'receipt_id'     => $receipt->id,
                    'invoice_id'     => $app['invoice_id'],
                    'applied_amount' => $app['applied_amount'],
                ]);

                /** @var Invoice $invoice */
                $invoice = $invoices[$app['invoice_id']];
                $this->applyReceiptToInvoice($invoice, (float) $app['applied_amount']);
            }

            return $receipt->fresh(['customer', 'bankAccount.glAccount', 'arAccount', 'applications.invoice', 'journalEntry']);
        });
    }

    /**
     * Cancel a posted receipt: reverses the JE, decrements paid_amount on
     * each linked invoice, and downgrades invoice status from paid back to
     * confirmed when it's no longer fully paid.
     */
    public function cancel(Receipt $receipt): Receipt
    {
        if (!$receipt->isCancellable()) {
            throw new DomainException("Receipt {$receipt->receipt_number} cannot be cancelled (status: {$receipt->status}).");
        }

        return DB::transaction(function () use ($receipt) {
            $receipt->load(['applications.invoice', 'journalEntry']);

            if ($receipt->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $receipt->journalEntry,
                    "RCPT-{$receipt->receipt_number}-CANCEL",
                    "Cancellation of receipt {$receipt->receipt_number}",
                );
                $receipt->reversal_journal_entry_id = $reversal->id;
            }

            foreach ($receipt->applications as $app) {
                $invoice = $app->invoice;
                if ($invoice) {
                    $this->unapplyReceiptFromInvoice($invoice, (float) $app->applied_amount);
                }
            }

            $receipt->status = Receipt::STATUS_CANCELLED;
            $receipt->save();

            return $receipt->fresh(['customer', 'bankAccount.glAccount', 'arAccount', 'applications.invoice', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    // ----- Helpers ----------------------------------------------------------

    private function normalizeApplications(array $raw): array
    {
        $out = [];
        foreach ($raw as $a) {
            if (empty($a['invoice_id'])) continue;
            $amt = round((float) ($a['applied_amount'] ?? 0), 2);
            if ($amt <= 0) continue;
            $out[] = ['invoice_id' => $a['invoice_id'], 'applied_amount' => $amt];
        }
        if (empty($out)) {
            throw new DomainException('A receipt must apply to at least one invoice.');
        }
        return $out;
    }

    private function assertInvoicesBelongToCustomer($invoices, string $customerId): void
    {
        foreach ($invoices as $invoice) {
            if ($invoice->customer_id !== $customerId) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} belongs to a different customer. " .
                    'A single receipt can only settle invoices for one customer.'
                );
            }
        }
    }

    private function assertInvoicesOpen($invoices): void
    {
        foreach ($invoices as $invoice) {
            if ($invoice->status !== Invoice::STATUS_CONFIRMED) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} is in status '{$invoice->status}' and cannot accept a receipt. " .
                    'Only confirmed invoices with outstanding balance accept receipts.'
                );
            }
            if ($this->outstandingOf($invoice) <= 0.001) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} has no outstanding balance."
                );
            }
        }
    }

    private function assertAppliedAmountsFitInvoices(array $applications, $invoices): void
    {
        foreach ($applications as $app) {
            $invoice = $invoices[$app['invoice_id']] ?? null;
            if (!$invoice) {
                throw new DomainException("Invoice {$app['invoice_id']} not found.");
            }
            $outstanding = $this->outstandingOf($invoice);
            if ($app['applied_amount'] > $outstanding + 0.001) {
                throw new DomainException(
                    "Applied amount {$app['applied_amount']} exceeds outstanding {$outstanding} on invoice {$invoice->invoice_number}."
                );
            }
        }
    }

    private function assertSumMatchesHeader(array $applications, float $headerAmount): void
    {
        $sum = round(array_sum(array_column($applications, 'applied_amount')), 2);
        if (abs($sum - round($headerAmount, 2)) > 0.001) {
            throw new DomainException(
                "Sum of applied amounts ({$sum}) does not equal receipt amount ({$headerAmount})."
            );
        }
    }

    private function applyReceiptToInvoice(Invoice $invoice, float $amount): void
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

    private function unapplyReceiptFromInvoice(Invoice $invoice, float $amount): void
    {
        $newPaid = max(0, round((float) $invoice->paid_amount - $amount, 2));

        // Don't touch cancelled invoices — their lifecycle is independent.
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            $invoice->forceFill(['paid_amount' => $newPaid])->save();
            return;
        }

        // Demote paid -> confirmed when the receipt that closed it goes away.
        // Stays paid only if it's still fully covered by other receipts.
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
