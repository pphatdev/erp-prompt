<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\EcomAddress;
use App\Models\Tenant\EcomCart;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockReservation;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use App\Tenants\Modules\Sales\Services\InvoiceService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Storefront checkout pipeline.
 *
 * Two-step flow:
 *   1. initiate(cart, provider, clientUuid)
 *        - Mints an EcomOrder in `pending_payment` and a pending EcomPayment.
 *        - Idempotent on (tenant_id, client_uuid): repeating the call returns
 *          the existing order/payment instead of creating a duplicate.
 *        - Snapshots shipping/billing address into the order.
 *        - Returns [order, payment]. Caller hands the payment id + amount to
 *          the gateway SDK on the storefront.
 *   2. confirm(order, providerPayload)
 *        - Validates the gateway charge (provider_charge_id + status).
 *        - Inside DB::transaction(): commits every cart reservation
 *          (releases stock-out movements), mints a Sales\Order shim,
 *          creates + confirms the Sales\Invoice (posts the AR journal),
 *          transitions the EcomOrder to `paid`, archives the cart.
 *
 * If the second step fails, the EcomOrder remains in `pending_payment` and
 * the cart's reservations stay active until the 15-min TTL expires — no
 * orphaned stock-outs, no half-posted invoices.
 */
class CheckoutService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly StockReservationService $reservations,
        private readonly AccountingService $accounting,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * Idempotent on (tenant_id, client_uuid). Returns ['order' => ..., 'payment' => ...].
     *
     * @return array{order: EcomOrder, payment: EcomPayment}
     */
    public function initiate(
        EcomCart $cart,
        string $clientUuid,
        string $provider,
        ?EcomAddress $shipping,
        ?EcomAddress $billing,
        ?EcomCustomer $guestCustomer = null
    ): array {
        if (!$cart->isActive()) {
            throw new DomainException("Cart is {$cart->status}; only active carts can be checked out.");
        }
        if ($cart->items()->count() === 0) {
            throw new DomainException('Cannot check out an empty cart.');
        }

        // Idempotent return on retry.
        $existingPayment = EcomPayment::where('client_uuid', $clientUuid)->first();
        if ($existingPayment) {
            return [
                'order' => $existingPayment->order->fresh(['items']),
                'payment' => $existingPayment,
            ];
        }

        $customerId = $cart->customer_id ?? $guestCustomer?->id;

        return DB::transaction(function () use ($cart, $clientUuid, $provider, $shipping, $billing, $customerId) {
            $order = EcomOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customerId,
                'cart_id' => $cart->id,
                'status' => EcomOrder::STATUS_PENDING_PAYMENT,
                'subtotal' => $cart->subtotal,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $cart->subtotal,
                'currency' => $cart->currency,
                'shipping_address' => $shipping?->toSnapshot(),
                'billing_address' => $billing?->toSnapshot() ?? $shipping?->toSnapshot(),
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $line) {
                $product = $line->product;
                $variant = $line->variant;
                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'variant_sku' => $variant?->sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->line_total,
                ]);
            }

            $payment = EcomPayment::create([
                'order_id' => $order->id,
                'provider' => $provider,
                'status' => EcomPayment::STATUS_PENDING,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'client_uuid' => $clientUuid,
            ]);

            return [
                'order' => $order->fresh('items'),
                'payment' => $payment,
            ];
        });
    }

    /**
     * Finalize a successful gateway charge. Atomically commits stock,
     * posts the AR invoice, and transitions the order.
     */
    public function confirm(EcomOrder $order, array $providerPayload): EcomOrder
    {
        if ($order->status === EcomOrder::STATUS_PAID
            || $order->status === EcomOrder::STATUS_FULFILLING
            || $order->status === EcomOrder::STATUS_SHIPPED
            || $order->status === EcomOrder::STATUS_DELIVERED
        ) {
            return $order;
        }
        if ($order->status !== EcomOrder::STATUS_PENDING_PAYMENT) {
            throw new DomainException(
                "Cannot confirm an order in status '{$order->status}'."
            );
        }

        return DB::transaction(function () use ($order, $providerPayload) {
            // 1. Mark the payment row succeeded.
            $payment = $order->payments()->latest('id')->first();
            if (!$payment) {
                throw new DomainException("Order {$order->order_number} has no pending payment to finalize.");
            }
            $payment->update([
                'status' => EcomPayment::STATUS_SUCCEEDED,
                'provider_charge_id' => $providerPayload['charge_id'] ?? null,
                'raw_payload' => $providerPayload,
                'captured_at' => now(),
                'gateway_fee' => (float) ($providerPayload['gateway_fee'] ?? 0),
            ]);

            // 2. Commit every reservation tied to the source cart.
            $cart = $order->cart;
            if ($cart) {
                foreach ($cart->items as $cartItem) {
                    if (!$cartItem->reservation_id) {
                        continue;
                    }
                    $reservation = StockReservation::find($cartItem->reservation_id);
                    if ($reservation && $reservation->isActive()) {
                        $this->reservations->commit($reservation);
                    }
                }
            }

            // 3. Mint a Sales\Order shim so the existing InvoiceService /
            //    accounting pipeline can post AR without forking logic.
            $salesOrder = $this->mintSalesOrderShim($order);

            // 4. Create + confirm the Sales\Invoice (posts AR journal).
            $invoice = $this->invoices->createFromOrder($salesOrder);
            $invoice = $this->invoices->confirm($invoice);

            // 4b. Post the cash receipt journal so AR clears immediately:
            //   DR Cash       (total - gateway_fee)
            //   DR Gateway Fee (gateway_fee)
            //   CR AR         (total)
            // Failure to resolve account codes logs and skips — the order
            // still books to AR via step 4, just without the receipt offset.
            $this->postCashReceiptJournal($order, $payment);

            // 5. Transition the EcomOrder and link downstream artifacts.
            $order->update([
                'status' => EcomOrder::STATUS_PAID,
                'paid_at' => now(),
                'sales_order_id' => $salesOrder->id,
                'invoice_id' => $invoice->id,
            ]);

            // 6. Archive the cart.
            if ($cart) {
                $cart->update([
                    'status' => EcomCart::STATUS_CONVERTED,
                    'converted_at' => now(),
                    'converted_order_id' => $order->id,
                ]);
            }

            return $order->fresh(['items', 'invoice', 'salesOrder']);
        });
    }

    /**
     * Cancel a pending-payment order: release reservations, mark payment failed,
     * transition the order. Terminal — caller starts a new checkout if needed.
     */
    public function cancel(EcomOrder $order, ?string $reason = null): EcomOrder
    {
        if ($order->status !== EcomOrder::STATUS_PENDING_PAYMENT) {
            throw new DomainException(
                "Only pending-payment orders can be cancelled here (status: {$order->status})."
            );
        }

        return DB::transaction(function () use ($order, $reason) {
            $cart = $order->cart;
            if ($cart) {
                foreach ($cart->items as $cartItem) {
                    if (!$cartItem->reservation_id) {
                        continue;
                    }
                    $reservation = StockReservation::find($cartItem->reservation_id);
                    if ($reservation && $reservation->isActive()) {
                        $this->reservations->cancel($reservation, 'checkout_cancelled');
                    }
                }
            }
            $pending = $order->payments()
                ->where('status', EcomPayment::STATUS_PENDING)
                ->get();
            foreach ($pending as $p) {
                $p->update([
                    'status' => EcomPayment::STATUS_FAILED,
                    'failure_code' => 'checkout_cancelled',
                    'failure_message' => $reason,
                    'failed_at' => now(),
                ]);
            }
            $order->update([
                'status' => EcomOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $order->fresh();
        });
    }

    /**
     * Create a Sales\Order representing this ecom order so the existing
     * InvoiceService can post AR. The Sales\Customer is resolved/created
     * lazily — one B2C umbrella customer per tenant keeps GL clean while
     * the EcomCustomer identity stays separate.
     */
    private function mintSalesOrderShim(EcomOrder $ecomOrder): Order
    {
        $umbrellaCustomer = $this->resolveB2CUmbrellaCustomer();

        $salesOrder = Order::create([
            'order_number' => 'ECOM-' . $ecomOrder->order_number,
            'customer_id' => $umbrellaCustomer->id,
            'status' => Order::STATUS_CONFIRM,
            'subtotal' => $ecomOrder->subtotal,
            'tax_amount' => $ecomOrder->tax_amount,
            'total_amount' => $ecomOrder->total_amount,
            'ordered_at' => now(),
            'confirmed_at' => now(),
            'due_date' => now()->toDateString(),
        ]);

        foreach ($ecomOrder->items as $line) {
            $product = Product::find($line->product_id);
            OrderItem::create([
                'order_id' => $salesOrder->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'product_name' => $line->product_name,
                'product_type' => $product?->product_type ?? Product::TYPE_HARDWARE,
                'variant_sku' => $line->variant_sku,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'total' => $line->line_total,
            ]);
        }

        return $salesOrder->fresh('items');
    }

    /**
     * Get or create the umbrella B2C customer that owns every storefront
     * order on the Sales side. Keeps the customer count sane while still
     * letting AR post normally.
     */
    private function resolveB2CUmbrellaCustomer(): Customer
    {
        $existing = Customer::where('external_code', 'ECOM-B2C')->first();
        if ($existing) {
            return $existing;
        }
        return Customer::create([
            'name' => 'Ecommerce B2C',
            'email' => null,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'external_code' => 'ECOM-B2C',
            'status' => 'active',
        ]);
    }

    /**
     * Post the cash receipt journal that clears AR after the Sales\Invoice
     * confirms. Soft-fails (logs + returns) if any required GL account is
     * missing so a misconfigured CoA doesn't block storefront checkout.
     */
    private function postCashReceiptJournal(EcomOrder $order, EcomPayment $payment): void
    {
        try {
            $cashCode = (string) $this->settings->get('ecommerce.cash_account_code', '1100');
            $arCode = (string) $this->settings->get('fms.ar_account_code', '1200');
            $feeCode = (string) $this->settings->get('ecommerce.gateway_fee_account_code', '6900');

            $cash = Account::where('code', $cashCode)->first();
            $ar = Account::where('code', $arCode)->first();
            if (!$cash || !$ar) {
                Log::warning('Ecom cash receipt journal skipped: missing GL account.', [
                    'order' => $order->order_number,
                    'cash_code' => $cashCode,
                    'ar_code' => $arCode,
                ]);
                return;
            }

            $total = round((float) $order->total_amount, 2);
            $fee = round((float) $payment->gateway_fee, 2);
            $cashNet = round($total - $fee, 2);

            $lines = [
                ['account_id' => $cash->id, 'debit' => $cashNet, 'credit' => 0],
            ];
            if ($fee > 0) {
                $feeAccount = Account::where('code', $feeCode)->first();
                if (!$feeAccount) {
                    Log::warning('Ecom gateway-fee account missing — rolling fee into cash.', [
                        'order' => $order->order_number,
                        'fee_code' => $feeCode,
                    ]);
                    $lines[0]['debit'] = $total;
                } else {
                    $lines[] = ['account_id' => $feeAccount->id, 'debit' => $fee, 'credit' => 0];
                }
            }
            $lines[] = ['account_id' => $ar->id, 'debit' => 0, 'credit' => $total];

            $this->accounting->postEntry([
                'reference_number' => 'ECOMR-' . $order->order_number,
                'description' => "Cash receipt for ecom order {$order->order_number}",
                'entry_date' => now()->toDateString(),
                'lines' => $lines,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ecom cash receipt journal failed; order still books to AR.', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateOrderNumber(): string
    {
        $prefix = $this->settings->get('numbering.ecommerce_order_prefix');
        if (empty($prefix)) {
            $prefix = 'ECOO-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
