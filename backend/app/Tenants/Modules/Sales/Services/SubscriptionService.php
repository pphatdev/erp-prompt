<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\SubscriptionItem;
use App\Tenants\Modules\Inventory\Services\PricingService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Subscription lifecycle service (target hybrid sales flow).
 *
 * Subscriptions start `active` the moment they're created from a confirmed
 * Sale Order. Status flow:
 *   active --renew-->     active  (extends end_date + new Invoice)
 *   active --upgrade-->   active  (variant swap + immediate delta Invoice)
 *   active --downgrade--> active  (variant swap + credit on next Invoice)
 *   active --cancel-->    cancelled (terminal)
 *   active --expire-->    expired (terminal, set by scheduled job)
 *   expired --renew-->    active  (creates a new subscription record)
 */
class SubscriptionService
{
    public function __construct(
        private readonly PricingService $pricing,
    ) {}

    public function createFromOrder(Order $order): ?Subscription
    {
        if (!$order->isConfirmed()) {
            throw new DomainException(
                "Order {$order->order_number} must be confirmed before creating a Subscription."
            );
        }

        $softwareItems = $order->items
            ->filter(fn ($item) => $item->product_type === Product::TYPE_SOFTWARE)
            ->values();

        if ($softwareItems->isEmpty()) {
            return null;
        }

        if ($order->subscription()->exists()) {
            return $order->subscription;
        }

        return DB::transaction(function () use ($order, $softwareItems) {
            $total = $softwareItems->sum('total');
            $start = $order->due_date ? Carbon::parse($order->due_date) : now()->startOfDay();
            $end   = $this->endDateFromCycle($start, Subscription::CYCLE_MONTHLY);

            $sub = Subscription::create([
                'subscription_number' => $this->generateSubscriptionNumber(),
                'order_id'            => $order->id,
                'customer_id'         => $order->customer_id,
                'status'              => Subscription::STATUS_ACTIVE,
                'start_date'          => $start->toDateString(),
                'end_date'            => $end->toDateString(),
                'billing_cycle'       => Subscription::CYCLE_MONTHLY,
                'total_amount'        => $total,
            ]);

            foreach ($softwareItems as $line) {
                SubscriptionItem::create([
                    'subscription_id' => $sub->id,
                    'order_item_id'   => $line->id,
                    'product_id'      => $line->product_id,
                    'variant_id'      => $line->variant_id,
                    'product_name'    => $line->product_name,
                    'variant_sku'     => $line->variant_sku,
                    'quantity'        => $line->quantity,
                    'unit_price'      => $line->unit_price,
                    'line_total'      => $line->total,
                ]);
            }

            return $sub->fresh('items');
        });
    }

    public function cancel(Subscription $sub, ?string $reason = null): Subscription
    {
        if ($sub->isCancelled()) {
            return $sub;
        }

        $sub->update([
            'status'        => Subscription::STATUS_CANCELLED,
            'cancelled_by'  => Auth::id(),
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);

        return $sub;
    }

    /**
     * Extend `end_date` by one billing cycle and issue a renewal Invoice
     * with the current line items snapshotted. Caller may override the
     * cycle (e.g. monthly → annual on renewal).
     */
    public function renew(Subscription $sub, ?string $cycle = null): Subscription
    {
        if ($sub->isCancelled()) {
            throw new DomainException('Cannot renew a cancelled subscription.');
        }
        if ($sub->items()->count() === 0) {
            throw new DomainException('Cannot renew a subscription with no items.');
        }

        $cycle = $cycle ?: $sub->billing_cycle;

        return DB::transaction(function () use ($sub, $cycle) {
            $base = $sub->end_date && $sub->end_date->isFuture()
                ? Carbon::parse($sub->end_date)
                : now();
            $newEnd = $this->endDateFromCycle($base, $cycle);

            $sub->update([
                'status'        => Subscription::STATUS_ACTIVE,
                'billing_cycle' => $cycle,
                'end_date'      => $newEnd->toDateString(),
            ]);

            $this->issueInvoiceFromSubscription(
                $sub->fresh(['items', 'customer']),
                $sub->items->sum('line_total'),
                "Renewal — {$sub->subscription_number}"
            );

            return $sub->fresh(['items', 'customer']);
        });
    }

    /**
     * Upgrade or downgrade a single line item. Upgrade bills the delta
     * immediately; downgrade emits a credit (negative-line Invoice).
     */
    public function changePlan(Subscription $sub, array $data, string $action): Subscription
    {
        if (!in_array($action, ['upgrade', 'downgrade'], true)) {
            throw new DomainException("Invalid changePlan action: {$action}.");
        }
        if ($sub->isCancelled() || $sub->isExpired()) {
            throw new DomainException("Cannot change plan on a {$sub->status} subscription.");
        }

        $resolved   = $this->pricing->resolveLine($data);
        $product    = $resolved['product'];
        $variant    = $resolved['variant'];
        $unitPrice  = $resolved['unit_price'];
        $variantSku = $resolved['variant_sku'];

        return DB::transaction(function () use ($sub, $product, $variant, $unitPrice, $variantSku, $data, $action) {
            // Locate the line to swap (by product_id; first match wins).
            /** @var SubscriptionItem|null $line */
            $line = $sub->items()->where('product_id', $data['target_product_id'] ?? $product->id)->first()
                  ?? $sub->items()->first();

            if (!$line) {
                throw new DomainException('Subscription has no items to swap.');
            }

            $oldTotal = (float) $line->line_total;
            $quantity = (float) $line->quantity;
            $newTotal = round($unitPrice * $quantity, 2);
            $delta    = round($newTotal - $oldTotal, 2);

            $line->update([
                'product_id'   => $product->id,
                'variant_id'   => $variant?->id,
                'product_name' => $product->name,
                'variant_sku'  => $variantSku,
                'unit_price'   => $unitPrice,
                'line_total'   => $newTotal,
            ]);

            $sub->update(['total_amount' => $sub->fresh('items')->items->sum('line_total')]);

            // Upgrade: bill the positive delta now. Downgrade: emit a credit
            // (negative-line Invoice) to be applied on the next cycle.
            if ($action === 'upgrade' && $delta > 0) {
                $this->issueInvoiceFromSubscription(
                    $sub->fresh(['items', 'customer']),
                    $delta,
                    "Upgrade delta — {$sub->subscription_number}"
                );
            }
            if ($action === 'downgrade' && $delta < 0) {
                $this->issueInvoiceFromSubscription(
                    $sub->fresh(['items', 'customer']),
                    $delta, // negative — represents a credit
                    "Downgrade credit — {$sub->subscription_number}"
                );
            }

            return $sub->fresh(['items', 'customer']);
        });
    }

    /**
     * Flip `active` subscriptions to `expired` when end_date is in the past.
     * Called from the scheduled `subscriptions:expire` command. Returns the
     * count of affected rows so callers can log progress.
     */
    public function expireDueSubscriptions(): int
    {
        return Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => Subscription::STATUS_EXPIRED]);
    }

    /**
     * Build an Invoice + InvoiceItems from the current subscription line
     * snapshot. Used by renew(), upgrade-delta, and downgrade-credit.
     */
    private function issueInvoiceFromSubscription(Subscription $sub, float $totalOverride, string $note): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'order_id'       => $sub->order_id,
            'customer_id'    => $sub->customer_id,
            'status'         => Invoice::STATUS_NEW,
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'subtotal'       => $totalOverride,
            'tax_amount'     => 0,
            'total_amount'   => $totalOverride,
            'notes'          => $note,
        ]);

        foreach ($sub->items as $line) {
            InvoiceItem::create([
                'invoice_id'    => $invoice->id,
                'product_id'    => $line->product_id,
                'variant_id'    => $line->variant_id,
                'product_name'  => $line->product_name,
                'product_type'  => Product::TYPE_SOFTWARE,
                'variant_sku'   => $line->variant_sku,
                'quantity'      => $line->quantity,
                'unit_price'    => $line->unit_price,
                'line_total'    => $line->line_total,
            ]);
        }

        return $invoice;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.invoice_prefix');
        if (empty($prefix)) {
            $prefix = 'INV-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Compute the end-date for a billing cycle starting at $start. Exposed
     * (public) so renewal logic in this service / future commands can reuse
     * the same arithmetic.
     */
    public function endDateFromCycle(Carbon $start, string $cycle): Carbon
    {
        return match ($cycle) {
            Subscription::CYCLE_MONTHLY => $start->copy()->addMonth(),
            Subscription::CYCLE_ANNUAL  => $start->copy()->addYear(),
            Subscription::CYCLE_ONE_TIME => $start->copy()->addYears(99),
            default => throw new DomainException("Unknown billing cycle: {$cycle}"),
        };
    }

    private function generateSubscriptionNumber(): string
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.subscription_prefix');
        if (empty($prefix)) {
            $prefix = 'SUB-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
