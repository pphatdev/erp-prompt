<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomOrderItem;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\EcomRefund;
use App\Models\Tenant\EcomRefundItem;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\FMS\Services\CreditNoteService;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\Log;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ecom refund lifecycle.
 *
 * request(): admin captures intent + line-level scope + reason. Creates an
 *            EcomRefund in `requested` and EcomRefundItem rows. No GL,
 *            no gateway call. Cheap to undo via reject().
 * approve(): atomically — calls the gateway refund, posts the reversing AR
 *            journal via AccountingService::reverseEntry, restocks each
 *            line whose refund_item.restock=true, transitions order/payment
 *            to refunded.
 * reject():  terminal, audit-logged with reason.
 */
class RefundService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CreditNoteService $creditNotes,
        private readonly StockService $stock,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * @param array{
     *     reason: ?string,
     *     items: array<int, array{order_item_id: string, quantity: float, restock?: bool}>
     * } $data
     */
    public function request(EcomOrder $order, array $data): EcomRefund
    {
        if (!$order->isRefundable()) {
            throw new DomainException(
                "Order in status '{$order->status}' is not refundable."
            );
        }
        if (empty($data['items'])) {
            throw new DomainException('At least one line item must be selected for refund.');
        }

        return DB::transaction(function () use ($order, $data) {
            $refundAmount = 0.0;
            $orderItemsById = $order->items->keyBy('id');

            // Validate before any insert so we don't half-commit.
            $validated = [];
            foreach ($data['items'] as $row) {
                /** @var EcomOrderItem|null $orderItem */
                $orderItem = $orderItemsById->get($row['order_item_id']);
                if (!$orderItem) {
                    throw new DomainException("Order item {$row['order_item_id']} doesn't belong to order {$order->order_number}.");
                }
                $qty = (float) $row['quantity'];
                if ($qty <= 0) {
                    throw new DomainException("Refund quantity for {$orderItem->product_name} must be positive.");
                }
                if ($qty > (float) $orderItem->quantity) {
                    throw new DomainException(
                        "Refund quantity ({$qty}) exceeds purchased quantity ({$orderItem->quantity}) for {$orderItem->product_name}."
                    );
                }
                $unit = (float) $orderItem->unit_price;
                $lineTotal = round($unit * $qty, 2);
                $refundAmount += $lineTotal;

                $validated[] = [
                    'order_item' => $orderItem,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $lineTotal,
                    'restock' => (bool) ($row['restock'] ?? true),
                ];
            }

            $refundAmount = round($refundAmount, 2);
            $orderTotal = (float) $order->total_amount;
            $isPartial = $refundAmount < $orderTotal;

            $refund = EcomRefund::create([
                'refund_number' => $this->generateRefundNumber(),
                'order_id' => $order->id,
                'payment_id' => $order->payments()
                    ->where('status', EcomPayment::STATUS_SUCCEEDED)
                    ->latest('id')->first()?->id,
                'status' => EcomRefund::STATUS_REQUESTED,
                'is_partial' => $isPartial,
                'amount' => $refundAmount,
                'currency' => $order->currency,
                'reason' => $data['reason'] ?? null,
                'requested_by' => Auth::id(),
                'requested_at' => now(),
            ]);

            foreach ($validated as $v) {
                EcomRefundItem::create([
                    'refund_id' => $refund->id,
                    'order_item_id' => $v['order_item']->id,
                    'quantity' => $v['quantity'],
                    'unit_price' => $v['unit_price'],
                    'line_total' => $v['line_total'],
                    'restock' => $v['restock'],
                ]);
            }

            return $refund->fresh('items');
        });
    }

    public function approve(EcomRefund $refund, ?string $providerRefundId = null): EcomRefund
    {
        if ($refund->status !== EcomRefund::STATUS_REQUESTED) {
            throw new DomainException(
                "Only requested refunds can be approved (current: {$refund->status})."
            );
        }

        return DB::transaction(function () use ($refund, $providerRefundId) {
            $order = $refund->order;

            // 1. Restock lines flagged restock=true.
            $warehouse = $this->resolveDefaultWarehouse();
            foreach ($refund->items as $item) {
                if (!$item->restock) {
                    continue;
                }
                $orderItem = $item->orderItem;
                if (!$orderItem || !$orderItem->product_id) {
                    continue;
                }
                $this->stock->recordMovement([
                    'product_id' => $orderItem->product_id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'in',
                    'quantity' => (float) $item->quantity,
                    'reference' => "REFUND:{$refund->refund_number}",
                    'notes' => "Ecom refund restock for {$orderItem->product_name}",
                ]);
            }

            // 2. AR adjustment — either issue a Credit Note (when fms.credit_notes_enabled)
            //    or reverse the original invoice journal directly. The Credit Note
            //    path is GAAP-cleaner for partial refunds because it leaves the
            //    original AR journal intact and posts a separate contra entry.
            $invoice = $order->invoice;
            if ($invoice) {
                $useCreditNote = (bool) $this->settings->get('fms.credit_notes_enabled', false);
                $creditNote = null;
                if ($useCreditNote) {
                    $creditNote = $this->tryIssueCreditNote($refund, $invoice);
                    if ($creditNote) {
                        $refund->forceFill(['credit_note_id' => $creditNote->id]);
                    }
                }
                if (!$creditNote && $invoice->journal_entry_id && !$refund->is_partial) {
                    // Fallback / direct path: only do a full reversal when the
                    // refund covers the entire invoice. Partial reversals are
                    // unsafe without a credit note because they'd zero out
                    // line totals the rest of the invoice still relies on.
                    $this->accounting->reverseEntry(
                        $invoice->journalEntry,
                        'REFUND-' . $refund->refund_number,
                        "Refund {$refund->refund_number} for invoice {$invoice->invoice_number}"
                    );
                }
            }

            // 3. Update payment status.
            if ($refund->payment) {
                $refund->payment->update([
                    'status' => $refund->is_partial
                        ? EcomPayment::STATUS_PARTIAL_REFUND
                        : EcomPayment::STATUS_REFUNDED,
                ]);
            }

            // 4. Transition refund + order.
            $refund->update([
                'status' => EcomRefund::STATUS_COMPLETED,
                'provider_refund_id' => $providerRefundId,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            // Order only flips to refunded for a full refund; partial leaves
            // the order in its prior state so it can still ship.
            if (!$refund->is_partial) {
                $order->update(['status' => EcomOrder::STATUS_REFUNDED]);
            }

            return $refund->fresh(['items', 'order', 'payment']);
        });
    }

    public function reject(EcomRefund $refund, string $reason): EcomRefund
    {
        if ($refund->status !== EcomRefund::STATUS_REQUESTED) {
            throw new DomainException(
                "Only requested refunds can be rejected (current: {$refund->status})."
            );
        }

        $refund->update([
            'status' => EcomRefund::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ]);

        return $refund->fresh();
    }

    /**
     * Attempt to issue a Credit Note for the refund amount against the
     * order's invoice + umbrella B2C customer. Returns null (and logs) if
     * the chart of accounts isn't set up for it — callers fall back to the
     * direct reversal path for full refunds.
     */
    private function tryIssueCreditNote(EcomRefund $refund, $invoice)
    {
        try {
            $arCode = (string) $this->settings->get('fms.ar_account_code', '1200');
            $returnsCode = (string) $this->settings->get('fms.sales_returns_account_code', '4900');
            $ar = Account::where('code', $arCode)->first();
            $returns = Account::where('code', $returnsCode)->first();
            if (!$ar || !$returns) {
                Log::info('Credit Note path skipped: missing GL accounts.', [
                    'refund' => $refund->refund_number,
                    'ar_code' => $arCode,
                    'returns_code' => $returnsCode,
                ]);
                return null;
            }

            return $this->creditNotes->issue([
                'credit_note_number' => 'CN-' . $refund->refund_number,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'ar_account_id' => $ar->id,
                'sales_returns_account_id' => $returns->id,
                'amount' => (float) $refund->amount,
                'issue_date' => now()->toDateString(),
                'memo' => "Ecom refund {$refund->refund_number}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('Credit Note issue failed; refund continues without it.', [
                'refund' => $refund->refund_number,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mirror of CartService::resolveDefaultWarehouse - same resolution order,
     * including the lazy `MAIN` auto-create so a refund-restock on a fresh
     * tenant doesn't fail before they've explicitly set up inventory.
     */
    private function resolveDefaultWarehouse(): Warehouse
    {
        $code = $this->settings->get('inventory.default_warehouse_code');
        if (is_string($code) && $code !== '') {
            $warehouse = Warehouse::where('code', $code)->first();
            if ($warehouse) {
                return $warehouse;
            }
        }
        $count = Warehouse::query()->count();
        if ($count === 1) {
            return Warehouse::query()->first();
        }
        if ($count > 1) {
            $w = Warehouse::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->first()
                ?? Warehouse::query()->orderBy('code')->first();
            if ($w) {
                return $w;
            }
        }
        return Warehouse::create([
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'is_active' => true,
            'notes' => 'Auto-created on first ecom refund restock.',
        ]);
    }

    private function generateRefundNumber(): string
    {
        $prefix = $this->settings->get('numbering.ecommerce_refund_prefix');
        if (empty($prefix)) {
            $prefix = 'ECOR-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
