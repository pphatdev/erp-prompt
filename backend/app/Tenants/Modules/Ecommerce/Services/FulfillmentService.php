<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Services;

use App\Models\Tenant\EcomOrder;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-side fulfillment transitions for ecom orders. Stock is already
 * decremented at CheckoutService::confirm — this layer just records
 * carrier/tracking and walks the FSM.
 */
class FulfillmentService
{
    public function markFulfilling(EcomOrder $order): EcomOrder
    {
        if ($order->status !== EcomOrder::STATUS_PAID) {
            throw new DomainException(
                "Only paid orders can be transitioned to fulfilling (current: {$order->status})."
            );
        }
        $order->update(['status' => EcomOrder::STATUS_FULFILLING]);
        return $order->fresh();
    }

    public function ship(EcomOrder $order, array $data): EcomOrder
    {
        if (!in_array($order->status, [EcomOrder::STATUS_PAID, EcomOrder::STATUS_FULFILLING], true)) {
            throw new DomainException(
                "Cannot ship order in status '{$order->status}'."
            );
        }
        if (empty($data['carrier']) || empty($data['tracking_number'])) {
            throw new DomainException('carrier and tracking_number are required to mark an order shipped.');
        }

        $order->update([
            'status' => EcomOrder::STATUS_SHIPPED,
            'carrier' => $data['carrier'],
            'tracking_number' => $data['tracking_number'],
            'shipped_at' => now(),
        ]);

        return $order->fresh();
    }

    public function markDelivered(EcomOrder $order): EcomOrder
    {
        if ($order->status !== EcomOrder::STATUS_SHIPPED) {
            throw new DomainException(
                "Only shipped orders can be marked delivered (current: {$order->status})."
            );
        }
        $order->update([
            'status' => EcomOrder::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        return $order->fresh();
    }
}
