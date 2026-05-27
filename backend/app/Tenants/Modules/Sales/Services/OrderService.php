<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Quotation;
use App\Tenants\Modules\Inventory\Services\PricingService;
use App\Tenants\Modules\Sales\Services\Fulfillment\OrderFulfillmentService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sales Order service (target hybrid sales flow).
 *
 * Status flow:
 *   draft   --confirm--> confirm  (runs Fulfillment + Tenant Provisioning)
 *   draft   --cancel-->  cancel   (terminal)
 *   confirm --cancel-->  ❌ rejected (reverse downstream individually)
 *
 * createFromQuotation() is the canonical entry point — called from
 * QuotationService::win. Ad-hoc createOrder() remains for dev/test use.
 */
class OrderService
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
        private readonly TenantProvisioningService $provisioner,
        private readonly PricingService $pricing,
    ) {
    }

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id'  => $data['customer_id'],
                'status'       => Order::STATUS_DRAFT,
                'due_date'     => $data['due_date'] ?? null,
                'total_amount' => 0,
                'subtotal'     => 0,
                'tax_amount'   => 0,
            ]);

            foreach ($data['items'] as $row) {
                $this->buildItem($order, $row);
            }

            return $this->recalcTotals($order->fresh('items'));
        });
    }

    /**
     * Snapshot a Won Quotation into a draft Sale Order. Called from
     * QuotationService::win inside that service's transaction.
     */
    public function createFromQuotation(Quotation $quote): Order
    {
        if (!$quote->isWon()) {
            throw new DomainException(
                "Quotation {$quote->quote_number} must be won before creating a Sale Order (currently '{$quote->status}')."
            );
        }
        if ($quote->order()->exists()) {
            return $quote->order;
        }

        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'quotation_id' => $quote->id,
            'customer_id'  => $quote->customer_id,
            'status'       => Order::STATUS_DRAFT,
            'due_date'     => $quote->due_date,
            'subtotal'     => $quote->subtotal,
            'tax_amount'   => $quote->tax_amount,
            'total_amount' => $quote->total_amount,
        ]);

        foreach ($quote->items as $line) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $line->product_id,
                'variant_id'   => $line->variant_id,
                'product_name' => $line->product_name,
                'product_type' => $line->product_type,
                'variant_sku'  => $line->variant_sku,
                'quantity'     => $line->quantity,
                'unit_price'   => $line->unit_price,
                'total'        => $line->line_total,
                'due_date'     => $line->due_date,
                'notes'        => $line->notes,
            ]);
        }

        return $order->fresh('items');
    }

    /**
     * Confirm an Order. The downstream fulfillment split (Invoice + Subscription +
     * Stock) runs inside the same transaction. Tenant provisioning runs AFTER
     * the transaction commits so the listener sees committed data and can open
     * its own DB connections safely.
     */
    public function confirmOrder(Order $order): Order
    {
        if ($order->isCancelled()) {
            throw new DomainException('Cannot confirm a cancelled order.');
        }
        if ($order->isConfirmed()) {
            return $order;
        }
        if ($order->items()->count() === 0) {
            throw new DomainException('Cannot confirm an order with no items.');
        }

        $confirmed = DB::transaction(function () use ($order) {
            $order->update([
                'status'       => Order::STATUS_CONFIRM,
                'ordered_at'   => now(),
                'confirmed_at' => now(),
            ]);

            $this->fulfillment->fulfill($order->fresh(['items', 'customer']));

            return $order->fresh(['items', 'customer', 'invoice', 'subscription']);
        });

        $this->provisionIfTenantCustomer($confirmed);

        return $confirmed->fresh(['items', 'customer', 'invoice', 'subscription']);
    }

    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        if ($order->isCancelled()) {
            return $order;
        }
        if ($order->isConfirmed()) {
            throw new DomainException(
                'Cannot cancel a confirmed order. Issue a credit note / cancel downstream artifacts instead.'
            );
        }

        $order->update([
            'status'        => Order::STATUS_CANCEL,
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);

        return $order;
    }

    /**
     * Trigger tenant provisioning if (1) customer is a tenant-type, (2) not
     * yet provisioned, (3) order has at least one software line (i.e. has a
     * Subscription). Failures are logged but do NOT roll back the committed
     * order.
     */
    private function provisionIfTenantCustomer(Order $order): void
    {
        try {
            /** @var Customer|null $customer */
            $customer = $order->customer;
            if (!$customer || !$customer->isTenantCustomer()) {
                return;
            }
            if ($customer->isProvisioned() && !$order->subscription) {
                return;
            }

            $sub = $order->subscription;
            if (!$sub && !$customer->isProvisioned()) {
                // Hardware-only order for a brand-new tenant customer. Spec
                // requires software for provisioning; skip silently.
                return;
            }

            $this->provisioner->provisionForCustomer($customer, $sub);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed after order confirm — order remains confirmed.', [
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function buildItem(Order $order, array $row): OrderItem
    {
        $resolved  = $this->pricing->resolveLine($row);
        $product   = $resolved['product'];
        $unitPrice = $resolved['unit_price'];
        $quantity  = (float) $row['quantity'];

        return OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'variant_id'   => $resolved['variant']?->id,
            'product_name' => $product->name,
            'product_type' => $product->product_type,
            'variant_sku'  => $resolved['variant_sku'],
            'quantity'     => $quantity,
            'unit_price'   => $unitPrice,
            'total'        => round($unitPrice * $quantity, 2),
            'due_date'     => $row['due_date'] ?? null,
            'notes'        => $row['notes'] ?? null,
        ]);
    }

    private function recalcTotals(Order $order): Order
    {
        $subtotal = $order->items->sum('total');
        $order->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => 0,
            'total_amount' => $subtotal,
        ]);

        return $order->fresh('items');
    }

    private function generateOrderNumber(): string
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.order_prefix');
        if (empty($prefix)) {
            $prefix = 'SO-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
