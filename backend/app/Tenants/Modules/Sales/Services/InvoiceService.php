<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Order;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invoice lifecycle service.
 *
 * createFromOrder() snapshots an Order's items into a 1:1 Invoice.
 * confirm() posts the canonical AR journal entry through AccountingService:
 *
 *   DR Accounts Receivable      total_amount
 *     CR Sales Revenue          subtotal
 *     CR Sales Tax Payable      tax_amount   (when non-zero)
 *
 * Account codes are resolved via SettingService keys
 * `fms.ar_account_code` / `fms.revenue_account_code` / `fms.tax_account_code`,
 * defaulting to a conventional GAAP-flavored chart (1200/4000/2150) — tenant
 * admins override per CoA without touching code.
 */
class InvoiceService
{
    private const DEFAULT_AR_CODE = '1200';
    private const DEFAULT_REVENUE_CODE = '4000';
    private const DEFAULT_TAX_CODE = '2150';

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SettingService $settings,
    ) {
    }

    public function createFromOrder(Order $order): Invoice
    {
        if (!$order->isConfirmed()) {
            throw new DomainException(
                "Order {$order->order_number} must be confirmed before invoicing (currently '{$order->status}')."
            );
        }
        if ($order->invoice()->exists()) {
            return $order->invoice;
        }

        return DB::transaction(function () use ($order) {
            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => Invoice::STATUS_NEW,
                'invoice_date' => now()->toDateString(),
                'due_date' => $order->due_date ?? now()->addDays(30)->toDateString(),
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
            ]);

            foreach ($order->items as $line) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'order_item_id' => $line->id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'product_name' => $line->product_name,
                    'product_type' => $line->product_type,
                    'variant_sku' => $line->variant_sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->total,
                ]);
            }

            return $invoice->fresh('items');
        });
    }

    public function confirm(Invoice $invoice): Invoice
    {
        if ($invoice->isCancelled()) {
            throw new DomainException('Cannot confirm a cancelled invoice.');
        }
        if ($invoice->isConfirmed()) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice) {
            $journal = $this->postArJournal($invoice);

            $invoice->update([
                'status' => Invoice::STATUS_CONFIRMED,
                'journal_entry_id' => $journal->id,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            return $invoice->fresh(['items', 'journalEntry']);
        });
    }

    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        if ($invoice->isCancelled()) {
            return $invoice;
        }
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new DomainException('Cannot cancel a paid invoice. Issue a credit note instead.');
        }
        // Confirmed-but-unpaid: cancellation must reverse the journal entry.
        // For now we forbid this and require explicit credit-note flow.
        if ($invoice->isConfirmed()) {
            throw new DomainException(
                'Cannot cancel a confirmed invoice directly. Post a reversal/credit note via FMS first.'
            );
        }

        $invoice->update([
            'status' => Invoice::STATUS_CANCELLED,
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $invoice;
    }

    /**
     * Build and post the AR journal entry. Returns the created JournalEntry.
     */
    private function postArJournal(Invoice $invoice)
    {
        $arCode = (string) $this->settings->get('fms.ar_account_code', self::DEFAULT_AR_CODE);
        $revenueCode = (string) $this->settings->get('fms.revenue_account_code', self::DEFAULT_REVENUE_CODE);
        $taxCode = (string) $this->settings->get('fms.tax_account_code', self::DEFAULT_TAX_CODE);

        $ar = $this->requireAccount($arCode, 'Accounts Receivable');
        $revenue = $this->requireAccount($revenueCode, 'Sales Revenue');

        $lines = [
            ['account_id' => $ar->id, 'debit' => (float) $invoice->total_amount, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => (float) $invoice->subtotal],
        ];

        if ((float) $invoice->tax_amount > 0) {
            $tax = $this->requireAccount($taxCode, 'Sales Tax Payable');
            $lines[] = ['account_id' => $tax->id, 'debit' => 0, 'credit' => (float) $invoice->tax_amount];
        }

        return $this->accounting->postEntry([
            'reference_number' => 'INV-' . $invoice->invoice_number,
            'description' => "AR for invoice {$invoice->invoice_number}",
            'entry_date' => $invoice->invoice_date,
            'lines' => $lines,
        ]);
    }

    private function requireAccount(string $code, string $label): Account
    {
        $account = Account::where('code', $code)->first();
        if (!$account) {
            throw new DomainException(
                "Chart of Accounts is missing the '{$label}' account (code: {$code}). " .
                "Seed it or set the matching `fms.*_account_code` in Settings."
            );
        }

        return $account;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = $this->settings->get('numbering.invoice_prefix');
        if (empty($prefix)) {
            $prefix = 'INV-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
