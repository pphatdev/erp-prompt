<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get('numbering.subscription_prefix') ?: 'SUB-';
        $subscriptionNumber = $this->subscription_number;
        if ($subscriptionNumber && preg_match('/(\d+)$/', $subscriptionNumber, $matches)) {
            $subscriptionNumber = $prefix . $matches[1];
        }

        return [
            'id' => $this->id,
            'subscriptionNumber' => $subscriptionNumber,
            'orderId' => $this->order_id,
            'customerId' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'billingCycle' => $this->billing_cycle,
            'totalAmount' => (float) $this->total_amount,
            'provisionedTenantId' => $this->provisioned_tenant_id,
            'provisionedAt' => optional($this->provisioned_at)->toIso8601String(),
            // Live access URL the customer can click — composed off the
            // owning Customer's tenant handle. Null until provisioning has
            // completed (typically right after Order::confirm). Lazy-loads
            // the customer relation when not already eager-loaded so this
            // never N+1s a list call but always works for show().
            'liveAccessUrl' => $this->loadMissing('customer')->customer?->liveAccessUrl(),
            'tenantHandle'  => $this->loadMissing('customer')->customer?->tenant_handle,
            'confirmedAt' => optional($this->confirmed_at)->toIso8601String(),
            'cancelledAt' => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'orderItemId' => $i->order_item_id,
                'productId' => $i->product_id,
                'variantId' => $i->variant_id,
                'productName' => $i->product_name,
                'variantSku' => $i->variant_sku,
                'quantity' => (float) $i->quantity,
                'unitPrice' => (float) $i->unit_price,
                'lineTotal' => (float) $i->line_total,
            ])),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
