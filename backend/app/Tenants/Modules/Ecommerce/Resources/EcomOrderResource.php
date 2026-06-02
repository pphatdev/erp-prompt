<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerId' => $this->customer_id,
            'cartId' => $this->cart_id,
            'salesOrderId' => $this->sales_order_id,
            'invoiceId' => $this->invoice_id,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'shippingAmount' => (float) $this->shipping_amount,
            'discountAmount' => (float) $this->discount_amount,
            'totalAmount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'shippingAddress' => $this->shipping_address,
            'billingAddress' => $this->billing_address,
            'carrier' => $this->carrier,
            'trackingNumber' => $this->tracking_number,
            'shippedAt' => optional($this->shipped_at)->toIso8601String(),
            'deliveredAt' => optional($this->delivered_at)->toIso8601String(),
            'placedAt' => optional($this->placed_at)->toIso8601String(),
            'paidAt' => optional($this->paid_at)->toIso8601String(),
            'cancelledAt' => optional($this->cancelled_at)->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'notes' => $this->notes,
            // Closure form: when the relation isn't eager-loaded, whenLoaded()
            // returns a MissingValue sentinel that filter() strips during
            // resolve(). Wrapping the MissingValue directly in a Resource
            // constructor (the eager form) ships a corrupted Resource that
            // later crashes with "Undefined property: MissingValue::$id".
            'customer' => $this->whenLoaded('customer', fn () => new EcomCustomerResource($this->customer)),
            'items'    => $this->whenLoaded('items',    fn () => EcomOrderItemResource::collection($this->items)),
            'payments' => $this->whenLoaded('payments', fn () => EcomPaymentResource::collection($this->payments)),
            'refunds'  => $this->whenLoaded('refunds',  fn () => EcomRefundResource::collection($this->refunds)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
