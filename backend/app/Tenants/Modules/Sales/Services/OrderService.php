<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Quotation;
use App\Tenants\Modules\Sales\Services\Fulfillment\OrderFulfillmentService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sales Order service.
 *
 * Two entry points:
 *   1. createOrder() — standalone (no quote upstream), kept for backwards-
 *      compat with existing API consumers and ad-hoc orders.
 *   2. createFromQuotation() — the canonical path in the hybrid flow.
 *
 * Status flow: new → confirmed → (fulfilled across Invoice/Subscription/Stock)
 *              new → cancelled (terminal)
 *
 * On confirm(), the OrderFulfillmentService is dispatched: invoice always,
 * subscription for software lines, stock deduction for hardware lines.
 */
class OrderService
{
    public function __construct(private readonly OrderFulfillmentService $fulfillment)
    {
    }

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $data['customer_id'],
                'status' => Order::STATUS_NEW,
                'due_date' => $data['due_date'] ?? null,
                'total_amount' => 0,
                'subtotal' => 0,
                'tax_amount' => 0,
            ]);

            foreach ($data['items'] as $row) {
                $this->buildItem($order, $row);
            }

            return $this->recalcTotals($order->fresh('items'));
        });
    }

    /**
     * Canonical hybrid-sales entry point — converts a confirmed Quotation
     * into a Sales Order with snapshotted line items. The quote is locked
     * (cannot be edited) once an Order exists for it.
     */
    public function createFromQuotation(Quotation $quote): Order
    {
        if (!$quote->isConfirmed()) {
            throw new DomainException(
                "Quotation {$quote->quote_number} must be confirmed before creating a Sales Order (currently '{$quote->status}')."
            );
        }
        if ($quote->order()->exists()) {
            throw new DomainException(
                "Sales Order already exists for quotation {$quote->quote_number}."
            );
        }

        return DB::transaction(function () use ($quote) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'quotation_id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'status' => Order::STATUS_NEW,
                'due_date' => $quote->due_date,
                'subtotal' => $quote->subtotal,
                'tax_amount' => $quote->tax_amount,
                'total_amount' => $quote->total_amount,
            ]);

            foreach ($quote->items as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'product_name' => $line->product_name,
                    'product_type' => $line->product_type,
                    'variant_sku' => $line->variant_sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total' => $line->line_total,
                    'due_date' => $line->due_date,
                    'notes' => $line->notes,
                ]);
            }

            return $order->fresh('items');
        });
    }

    /**
     * Confirm an Order. The downstream fulfillment split (Invoice +
     * Subscription + Stock) runs inside the same transaction so partial
     * states never escape on failure.
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

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => Order::STATUS_CONFIRMED,
                'ordered_at' => now(),
                'confirmed_at' => now(),
            ]);

            $this->fulfillment->fulfill($order->fresh(['items', 'customer']));

            return $order->fresh(['items', 'invoice', 'subscription']);
        });
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
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $order;
    }

    private function buildItem(Order $order, array $row): OrderItem
    {
        /** @var Product $product */
        $product = Product::findOrFail($row['product_id']);
        $variant = null;
        $unitPrice = (float) $product->unit_price;
        $variantSku = null;

        if (!empty($row['variant_id'])) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::where('product_id', $product->id)->findOrFail($row['variant_id']);
            $unitPrice = (float) $variant->unit_price;
            $variantSku = $variant->sku;
        }

        if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null) {
            $unitPrice = (float) $row['unit_price'];
        }

        $quantity = (float) $row['quantity'];

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_type' => $product->product_type,
            'variant_sku' => $variantSku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($unitPrice * $quantity, 2),
            'due_date' => $row['due_date'] ?? null,
            'notes' => $row['notes'] ?? null,
        ]);
    }

    private function recalcTotals(Order $order): Order
    {
        $subtotal = $order->items->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
        ]);

        return $order->fresh('items');
    }

    private function generateOrderNumber(): string
    {
        return 'SO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
