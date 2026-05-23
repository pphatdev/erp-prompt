<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\QuotationItem;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quotation lifecycle service.
 *
 * Status flow: new → confirmed → (downstream Sales Order)
 *              new → cancelled (terminal)
 *
 * Line items snapshot product_name / product_type / unit_price at quote
 * time so subsequent catalogue edits never alter historical totals.
 */
class QuotationService
{
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $quote = Quotation::create([
                'quote_number' => $this->generateQuoteNumber(),
                'customer_id' => $data['customer_id'],
                'status' => Quotation::STATUS_NEW,
                'quote_date' => $data['quote_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $this->buildItem($quote, $row);
            }

            return $this->recalcTotals($quote->fresh('items'));
        });
    }

    /**
     * Append a line to an existing draft quotation. Confirmed/cancelled
     * quotes are locked.
     */
    public function addItem(Quotation $quote, array $row): QuotationItem
    {
        $this->assertEditable($quote);

        return DB::transaction(function () use ($quote, $row) {
            $item = $this->buildItem($quote, $row);
            $this->recalcTotals($quote->fresh('items'));

            return $item->fresh();
        });
    }

    public function confirm(Quotation $quote): Quotation
    {
        if ($quote->isCancelled()) {
            throw new DomainException('Cannot confirm a cancelled quotation.');
        }
        if ($quote->isConfirmed()) {
            return $quote;
        }
        if ($quote->items()->count() === 0) {
            throw new DomainException('Cannot confirm a quotation with no items.');
        }

        $quote->update([
            'status' => Quotation::STATUS_CONFIRMED,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        return $quote;
    }

    public function cancel(Quotation $quote, ?string $reason = null): Quotation
    {
        if ($quote->isCancelled()) {
            return $quote;
        }
        // Confirmed quotes can still be cancelled IF no Sales Order was made
        // from them. Once an Order exists, cancellation flows through the SO.
        if ($quote->isConfirmed() && $quote->order()->exists()) {
            throw new DomainException(
                'Cannot cancel: a Sales Order has already been created from this quotation. Cancel the Sales Order instead.'
            );
        }

        $quote->update([
            'status' => Quotation::STATUS_CANCELLED,
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $quote;
    }

    private function buildItem(Quotation $quote, array $row): QuotationItem
    {
        /** @var Product $product */
        $product = Product::findOrFail($row['product_id']);

        $variant = null;
        $unitPrice = (float) $product->unit_price;
        $variantSku = null;

        if (!empty($row['variant_id'])) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::where('product_id', $product->id)
                ->findOrFail($row['variant_id']);
            $unitPrice = (float) $variant->unit_price;
            $variantSku = $variant->sku;
        }

        // Caller may explicitly override the unit price (negotiated discount).
        if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null) {
            $unitPrice = (float) $row['unit_price'];
        }

        $quantity = (float) $row['quantity'];

        return QuotationItem::create([
            'quotation_id' => $quote->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_type' => $product->product_type,
            'variant_sku' => $variantSku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $quantity, 2),
            'due_date' => $row['due_date'] ?? null,
            'notes' => $row['notes'] ?? null,
        ]);
    }

    private function recalcTotals(Quotation $quote): Quotation
    {
        $subtotal = $quote->items->sum('line_total');
        // Tax left as 0 by default — extend here when tax engine lands.
        $quote->update([
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
        ]);

        return $quote->fresh('items');
    }

    private function assertEditable(Quotation $quote): void
    {
        if ($quote->status !== Quotation::STATUS_NEW) {
            throw new DomainException(
                "Quotation {$quote->quote_number} is {$quote->status}; only 'new' quotes are editable."
            );
        }
    }

    private function generateQuoteNumber(): string
    {
        return 'QT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
