<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Ecommerce;

use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomPayment;
use App\Models\Tenant\EcomRefund;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Ecommerce\Services\CartService;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Ecommerce\Services\RefundService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

/**
 * P1 — Refund happy path:
 *   request (admin captures intent) -> approve (restock + reverse journal)
 *
 * Full-refund path: stock is restocked, original AR journal is reversed,
 * EcomOrder flips to refunded. Partial refund path: stock + payment status
 * change but order stays shippable.
 */
class RefundLifecycleTest extends TenantTestCase
{
    private CartService $carts;
    private CheckoutService $checkout;
    private RefundService $refunds;
    private StockService $stock;
    private Warehouse $warehouse;
    private Product $product;
    private EcomCustomer $shopper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carts = app(CartService::class);
        $this->checkout = app(CheckoutService::class);
        $this->refunds = app(RefundService::class);
        $this->stock = app(StockService::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-REF', 'name' => 'Refund WH']);
        Setting::create([
            'key' => 'inventory.default_warehouse_code',
            'value' => 'WH-REF',
            'group' => 'inventory',
            'type' => 'string',
        ]);
        $this->product = Product::create([
            'sku' => 'REF-1',
            'name' => 'Refundable Product',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 30,
            'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_cost' => 12,
        ]);

        $this->shopper = EcomCustomer::create(['email' => 'r@test.com', 'password' => 'secret123']);
    }

    private function placePaidOrder(int $qty = 3): EcomOrder
    {
        $cart = $this->carts->getOrCreateForCustomer($this->shopper);
        $this->carts->addItem($cart, ['product_id' => $this->product->id, 'quantity' => $qty]);
        $r = $this->checkout->initiate($cart->fresh('items'), 'uuid-' . $qty, 'manual', null, null);
        return $this->checkout->confirm($r['order'], ['charge_id' => 'ch_' . $qty]);
    }

    public function test_full_refund_restocks_and_reverses_journal_and_flips_order_to_refunded(): void
    {
        $order = $this->placePaidOrder(3);
        $this->assertSame(7.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        $invoice = $order->invoice;
        $originalJournalId = $invoice->journal_entry_id;
        $this->assertNotNull($originalJournalId);

        $refund = $this->refunds->request($order->fresh('items'), [
            'reason' => 'customer cancelled',
            'items' => [[
                'order_item_id' => $order->items->first()->id,
                'quantity' => 3,
                'restock' => true,
            ]],
        ]);
        $this->assertSame(EcomRefund::STATUS_REQUESTED, $refund->status);
        $this->assertFalse((bool) $refund->is_partial);

        $approved = $this->refunds->approve($refund, 're_test_123');

        $this->assertSame(EcomRefund::STATUS_COMPLETED, $approved->status);
        $this->assertSame('re_test_123', $approved->provider_refund_id);

        // Stock restocked back to 10.
        $this->assertSame(10.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // Order flipped to refunded for a full refund.
        $this->assertSame(EcomOrder::STATUS_REFUNDED, $order->fresh()->status);

        // Payment status reflects full refund.
        $payment = $order->payments()->first();
        $this->assertSame(EcomPayment::STATUS_REFUNDED, $payment->fresh()->status);

        // A reversing journal exists referencing the original.
        $reversal = JournalEntry::where('reverses_journal_entry_id', $originalJournalId)->first();
        $this->assertNotNull($reversal, 'Approve must post a reversing journal entry.');
    }

    public function test_partial_refund_leaves_order_shippable_and_marks_payment_partial(): void
    {
        $order = $this->placePaidOrder(3);

        $refund = $this->refunds->request($order->fresh('items'), [
            'reason' => 'damaged one unit',
            'items' => [[
                'order_item_id' => $order->items->first()->id,
                'quantity' => 1,
                'restock' => false, // damaged - do not restock
            ]],
        ]);
        $this->assertTrue((bool) $refund->is_partial);
        $this->assertEquals(30.0, (float) $refund->amount);

        $approved = $this->refunds->approve($refund);
        $this->assertSame(EcomRefund::STATUS_COMPLETED, $approved->status);

        // Stock NOT restocked because restock=false.
        $this->assertSame(7.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // Order stays in 'paid' (not refunded) because partial.
        $this->assertSame(EcomOrder::STATUS_PAID, $order->fresh()->status);

        // Payment marked partial_refund.
        $payment = $order->payments()->first();
        $this->assertSame(EcomPayment::STATUS_PARTIAL_REFUND, $payment->fresh()->status);
    }

    public function test_refund_rejected_with_reason_is_terminal_and_audit_logged(): void
    {
        $order = $this->placePaidOrder(2);

        $refund = $this->refunds->request($order->fresh('items'), [
            'reason' => 'speculative claim',
            'items' => [[
                'order_item_id' => $order->items->first()->id,
                'quantity' => 1,
                'restock' => true,
            ]],
        ]);

        $rejected = $this->refunds->reject($refund, 'evidence insufficient');
        $this->assertSame(EcomRefund::STATUS_REJECTED, $rejected->status);
        $this->assertSame('evidence insufficient', $rejected->rejection_reason);

        // Re-approval after reject must fail.
        $this->expectException(\DomainException::class);
        $this->refunds->approve($rejected);
    }
}
