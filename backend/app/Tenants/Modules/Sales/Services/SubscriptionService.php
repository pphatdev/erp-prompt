<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\SubscriptionItem;
use App\Tenants\Modules\Sales\Events\SubscriptionConfirmed;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Subscription lifecycle service.
 *
 * Subscriptions wrap the software-typed lines of a Sales Order. One
 * Subscription per Order (1:1 enforced at the DB layer). confirm() fires
 * SubscriptionConfirmed; the registered listener handles tenant provisioning.
 */
class SubscriptionService
{
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
            // Pure hardware order — no subscription needed. This is a normal
            // outcome, not an error.
            return null;
        }

        if ($order->subscription()->exists()) {
            return $order->subscription;
        }

        return DB::transaction(function () use ($order, $softwareItems) {
            $total = $softwareItems->sum('total');

            $sub = Subscription::create([
                'subscription_number' => $this->generateSubscriptionNumber(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => Subscription::STATUS_NEW,
                'start_date' => $order->due_date ?? now()->toDateString(),
                'billing_cycle' => Subscription::CYCLE_MONTHLY,
                'total_amount' => $total,
            ]);

            foreach ($softwareItems as $line) {
                SubscriptionItem::create([
                    'subscription_id' => $sub->id,
                    'order_item_id' => $line->id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'product_name' => $line->product_name,
                    'variant_sku' => $line->variant_sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->total,
                ]);
            }

            return $sub->fresh('items');
        });
    }

    public function confirm(Subscription $sub): Subscription
    {
        if ($sub->isCancelled()) {
            throw new DomainException('Cannot confirm a cancelled subscription.');
        }

        // If already confirmed but provisioning previously failed, re-dispatch so
        // the listener retries. TenantProvisioningService uses firstOrCreate, making
        // repeated dispatches safe.
        if ($sub->isConfirmed()) {
            if (empty($sub->provisioned_tenant_id)) {
                SubscriptionConfirmed::dispatch($sub->fresh());
            }
            return $sub->fresh();
        }

        DB::transaction(function () use ($sub) {
            $sub->update([
                'status' => Subscription::STATUS_CONFIRMED,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);
        });

        // Dispatch outside any transaction so the listener (ProvisionSubscriptionTenant)
        // sees the committed row and can safely open its own DB connections.
        SubscriptionConfirmed::dispatch($sub->fresh());

        return $sub->fresh();
    }

    public function cancel(Subscription $sub, ?string $reason = null): Subscription
    {
        if ($sub->isCancelled()) {
            return $sub;
        }

        $sub->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $sub;
    }

    private function generateSubscriptionNumber(): string
    {
        return 'SUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
