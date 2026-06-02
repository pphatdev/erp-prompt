<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosOrderItem;
use App\Models\Tenant\PosPayment;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomic POS checkout engine.
 *
 *   checkout(shift, items, payments, customer?, clientUuid?)
 *      - shift must be `open`
 *      - sum(payments.amount) must equal grand_total within 0.005
 *      - same client_uuid returns the existing order (offline-replay safe)
 *      - inside DB::transaction: line snapshots -> StockService::recordMovement
 *        type=out per line referenced as POS:{order_number} -> generate number
 *        from SettingService -> AccountingService::postEntry balanced journal
 *
 *   voidOrder(order, reason)
 *      - paid -> voided
 *      - reverses the original journal via AccountingService::reverseEntry
 *      - posts compensating in-movements so stock returns to the warehouse
 *
 * Journal recipe on checkout:
 *
 *   DR per payment method account (terminal's petty_cash_account_id wins for cash)
 *   CR sales revenue account                  (subtotal - discount_total)
 *   CR sales tax payable                      (tax_total)
 *
 * Default account codes come from SettingService:
 *   pos.cash_account_code           default 1100
 *   pos.card_account_code           default 1110
 *   pos.wallet_account_code         default 1120
 *   fms.revenue_account_code        default 4000
 *   fms.tax_account_code            default 2150
 *
 * Customer link is optional - walk-ins record customer_id = null.
 */
class PosOrderService
{
    private const DEFAULT_CASH_CODE    = '1100';
    private const DEFAULT_CARD_CODE    = '1110';
    private const DEFAULT_WALLET_CODE  = '1120';
    private const DEFAULT_REVENUE_CODE = '4000';
    private const DEFAULT_TAX_CODE     = '2150';

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly StockService $stock,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * @param array{
     *     items: array<int, array{product_id:string, variant_id?:?string, quantity:float, unit_price?:?float, discount?:?float, tax_amount?:?float}>,
     *     payments: array<int, array{payment_method:string, amount:float, tendered?:?float, reference_number?:?string}>,
     *     customer_id?: ?string,
     *     client_uuid?: ?string,
     *     notes?: ?string,
     * } $payload
     */
    public function checkout(PosShift $shift, array $payload): PosOrder
    {
        if (!$shift->isOpen()) {
            throw new DomainException("Cannot check out on a '{$shift->status}' shift.");
        }
        if (empty($payload['items'])) {
            throw new DomainException('Checkout requires at least one line item.');
        }
        if (empty($payload['payments'])) {
            throw new DomainException('Checkout requires at least one tender row.');
        }

        // Idempotent replay on client_uuid (offline sync).
        $clientUuid = $payload['client_uuid'] ?? null;
        if ($clientUuid) {
            $existing = PosOrder::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return $existing->fresh(['items', 'payments']);
            }
        }

        return DB::transaction(function () use ($shift, $payload, $clientUuid) {
            $shift->loadMissing('terminal');
            $terminal = $shift->terminal;
            if (!$terminal) {
                throw new DomainException('Shift has no terminal attached.');
            }

            $linePlan = $this->planLines($payload['items']);
            $totals = $this->sumLines($linePlan);
            $paymentPlan = $this->planPayments($payload['payments']);
            $paymentsTotal = round(array_sum(array_column($paymentPlan, 'amount')), 2);

            if (abs($paymentsTotal - $totals['grand_total']) > 0.005) {
                throw new DomainException(
                    "Tender total {$paymentsTotal} does not match grand total {$totals['grand_total']}."
                );
            }

            $order = PosOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'shift_id' => $shift->id,
                'terminal_id' => $terminal->id,
                'cashier_id' => $shift->cashier_id,
                'client_uuid' => $clientUuid,
                'customer_id' => $payload['customer_id'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'status' => PosOrder::STATUS_PAID,
                'placed_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($linePlan as $line) {
                PosOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'variant_id' => $line['variant']?->id,
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'variant_sku' => $line['variant']?->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                ]);

                $this->stock->recordMovement([
                    'product_id' => $line['product']->id,
                    'warehouse_id' => $terminal->warehouse_id,
                    'type' => 'out',
                    'quantity' => (float) $line['quantity'],
                    'reference' => "POS:{$order->order_number}",
                    'notes' => "POS sale - {$line['product']->name}",
                ]);
            }

            foreach ($paymentPlan as $p) {
                PosPayment::create([
                    'order_id' => $order->id,
                    'payment_method' => $p['payment_method'],
                    'amount' => $p['amount'],
                    'tendered' => $p['tendered'],
                    'change_due' => $p['change_due'],
                    'reference_number' => $p['reference_number'],
                ]);
            }

            $journal = $this->postSalesJournal($order, $paymentPlan, $terminal->petty_cash_account_id);
            $order->update(['journal_entry_id' => $journal->id]);

            return $order->fresh(['items', 'payments', 'journalEntry']);
        });
    }

    public function voidOrder(PosOrder $order, ?string $reason = null): PosOrder
    {
        if ($order->isVoided()) {
            return $order;
        }
        if (!$order->isPaid()) {
            throw new DomainException("Only paid orders can be voided (current: '{$order->status}').");
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->loadMissing(['items', 'terminal', 'journalEntry']);
            $terminal = $order->terminal;
            if (!$terminal) {
                throw new DomainException('Order has no terminal context; refusing to void.');
            }

            foreach ($order->items as $line) {
                if (!$line->product_id) {
                    continue;
                }
                $this->stock->recordMovement([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $terminal->warehouse_id,
                    'type' => 'in',
                    'quantity' => (float) $line->quantity,
                    'reference' => "POS-VOID:{$order->order_number}",
                    'notes' => 'POS void restock - ' . ($reason ?: 'admin void'),
                ]);
            }

            if ($order->journal_entry_id && $order->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $order->journalEntry,
                    'POS-VOID-' . $order->order_number,
                    "Void of POS order {$order->order_number}" . ($reason ? " - {$reason}" : '')
                );
                $order->update(['void_journal_entry_id' => $reversal->id]);
            }

            $order->update([
                'status' => PosOrder::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
            ]);

            return $order->fresh(['items', 'payments']);
        });
    }

    private function planLines(array $rawItems): array
    {
        $out = [];
        foreach ($rawItems as $row) {
            $product = Product::findOrFail($row['product_id']);
            $variant = !empty($row['variant_id']) ? ProductVariant::find($row['variant_id']) : null;
            $qty = (float) $row['quantity'];
            if ($qty <= 0) {
                throw new DomainException("Line quantity must be positive for {$product->name}.");
            }
            $unitPrice = isset($row['unit_price']) && $row['unit_price'] !== null
                ? (float) $row['unit_price']
                : (float) $product->unit_price;
            $discount = (float) ($row['discount'] ?? 0);
            $taxAmount = (float) ($row['tax_amount'] ?? 0);
            $lineTotal = round(($unitPrice * $qty) - $discount + $taxAmount, 2);

            $out[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ];
        }
        return $out;
    }

    private function sumLines(array $linePlan): array
    {
        $subtotalGross = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        foreach ($linePlan as $l) {
            $subtotalGross += $l['unit_price'] * $l['quantity'];
            $discountTotal += $l['discount'];
            $taxTotal += $l['tax_amount'];
        }
        $subtotal = round($subtotalGross, 2);
        $discountTotal = round($discountTotal, 2);
        $taxTotal = round($taxTotal, 2);
        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
        ];
    }

    private function planPayments(array $raw): array
    {
        $out = [];
        foreach ($raw as $p) {
            $method = $p['payment_method'] ?? '';
            if (!in_array($method, PosPayment::PAYMENT_METHODS, true)) {
                throw new DomainException("Unknown payment method '{$method}'.");
            }
            $amount = round((float) $p['amount'], 2);
            if ($amount <= 0) {
                throw new DomainException('Payment amount must be positive.');
            }
            $tendered = isset($p['tendered']) ? round((float) $p['tendered'], 2) : null;
            $changeDue = 0.0;
            if ($method === PosPayment::METHOD_CASH && $tendered !== null) {
                $changeDue = max(0, round($tendered - $amount, 2));
            }
            $out[] = [
                'payment_method' => $method,
                'amount' => $amount,
                'tendered' => $tendered,
                'change_due' => $changeDue,
                'reference_number' => $p['reference_number'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * DR per payment method (cash uses terminal's petty cash when set)
     * CR sales revenue            net of discount
     * CR sales tax payable        when tax_total > 0
     */
    private function postSalesJournal(PosOrder $order, array $paymentPlan, ?string $terminalPettyCashAccountId)
    {
        $cashCode    = (string) $this->settings->get('pos.cash_account_code', self::DEFAULT_CASH_CODE);
        $cardCode    = (string) $this->settings->get('pos.card_account_code', self::DEFAULT_CARD_CODE);
        $walletCode  = (string) $this->settings->get('pos.wallet_account_code', self::DEFAULT_WALLET_CODE);
        $revenueCode = (string) $this->settings->get('fms.revenue_account_code', self::DEFAULT_REVENUE_CODE);
        $taxCode     = (string) $this->settings->get('fms.tax_account_code', self::DEFAULT_TAX_CODE);

        $cashAccount = $terminalPettyCashAccountId
            ? Account::find($terminalPettyCashAccountId) ?? $this->requireAccount($cashCode, 'POS cash drawer')
            : $this->requireAccount($cashCode, 'POS cash drawer');
        $cardAccount   = null;
        $walletAccount = null;

        $lines = [];
        foreach ($paymentPlan as $p) {
            switch ($p['payment_method']) {
                case PosPayment::METHOD_CASH:
                    $lines[] = ['account_id' => $cashAccount->id, 'debit' => $p['amount'], 'credit' => 0];
                    break;
                case PosPayment::METHOD_CARD:
                    $cardAccount ??= $this->requireAccount($cardCode, 'POS card acquirer');
                    $lines[] = ['account_id' => $cardAccount->id, 'debit' => $p['amount'], 'credit' => 0];
                    break;
                case PosPayment::METHOD_WALLET:
                    $walletAccount ??= $this->requireAccount($walletCode, 'POS digital wallet');
                    $lines[] = ['account_id' => $walletAccount->id, 'debit' => $p['amount'], 'credit' => 0];
                    break;
                case PosPayment::METHOD_MANUAL:
                    // Manual / "house account" tender treated as cash for GL purposes.
                    $lines[] = ['account_id' => $cashAccount->id, 'debit' => $p['amount'], 'credit' => 0];
                    break;
            }
        }

        $revenueAccount = $this->requireAccount($revenueCode, 'Sales Revenue');
        $netRevenue = round((float) $order->subtotal - (float) $order->discount_total, 2);
        if ($netRevenue > 0) {
            $lines[] = ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $netRevenue];
        }
        if ((float) $order->tax_total > 0) {
            $taxAccount = $this->requireAccount($taxCode, 'Sales Tax Payable');
            $lines[] = ['account_id' => $taxAccount->id, 'debit' => 0, 'credit' => (float) $order->tax_total];
        }

        return $this->accounting->postEntry([
            'reference_number' => 'POS-' . $order->order_number,
            'description' => "POS sale {$order->order_number}",
            'entry_date' => now()->toDateString(),
            'lines' => $lines,
        ]);
    }

    private function requireAccount(string $code, string $label): Account
    {
        $account = Account::where('code', $code)->first();
        if (!$account) {
            throw new DomainException(
                "Chart of Accounts is missing the '{$label}' account (code: {$code}). " .
                'Seed it or set the matching `pos.*_account_code` / `fms.*_account_code` setting.'
            );
        }
        return $account;
    }

    private function generateOrderNumber(): string
    {
        $prefix = $this->settings->get('numbering.pos_order_prefix');
        if (empty($prefix)) {
            $prefix = 'POS-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
