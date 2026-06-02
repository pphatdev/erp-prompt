<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\POS;

use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosPayment;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\POS\Services\PosOrderService;
use App\Tenants\Modules\POS\Services\PosShiftService;
use Tests\Feature\TenantTestCase;

/**
 * P1 - POS checkout happy path + idempotency + tender-mismatch guard.
 *
 * After checkout:
 *   - EcomOrder -- sorry, PosOrder is `paid`
 *   - StockMovement type=out referenced "POS:{order_number}" was posted
 *   - journal_entry_id is populated
 *   - the same client_uuid returns the existing order on retry
 *   - the closing-shift expected_cash includes the cash payment
 */
class PosCheckoutLifecycleTest extends TenantTestCase
{
    private PosShiftService $shifts;
    private PosOrderService $orders;
    private StockService $stock;
    private PosTerminal $terminal;
    private User $cashier;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shifts = app(PosShiftService::class);
        $this->orders = app(PosOrderService::class);
        $this->stock = app(StockService::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-CO', 'name' => 'Checkout WH']);
        $this->terminal = PosTerminal::create([
            'code' => 'REG-CO',
            'name' => 'Checkout Register',
            'warehouse_id' => $this->warehouse->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $this->cashier = User::create([
            'name' => 'CO Cashier', 'email' => 'co@test.com', 'password' => 'secret123',
        ]);

        $this->product = Product::create([
            'sku' => 'POS-1',
            'name' => 'Coffee',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 4.50,
            'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 50,
            'unit_cost' => 2.00,
        ]);
    }

    public function test_checkout_decrements_stock_posts_journal_and_marks_paid(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);

        $order = $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 9.00,
                'tendered' => 10.00,
            ]],
        ]);

        $this->assertSame(PosOrder::STATUS_PAID, $order->status);
        $this->assertEquals(9.00, (float) $order->grand_total);
        $this->assertNotNull($order->journal_entry_id);
        $this->assertSame(48.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // Out movement referenced this order.
        $out = StockMovement::where('reference', "POS:{$order->order_number}")
            ->where('type', 'out')
            ->first();
        $this->assertNotNull($out);

        // Cash change recorded.
        $payment = $order->payments()->first();
        $this->assertEquals(1.00, (float) $payment->change_due);
    }

    public function test_duplicate_client_uuid_returns_existing_order(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        $payload = [
            'client_uuid' => 'pos-uuid-1',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 4.50,
                'tendered' => 5.00,
            ]],
        ];

        $first = $this->orders->checkout($shift->fresh(), $payload);
        $second = $this->orders->checkout($shift->fresh(), $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PosOrder::where('client_uuid', 'pos-uuid-1')->count());
        $this->assertSame(49.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id),
            'Replay must not double-deduct stock.');
    }

    public function test_tender_total_must_match_grand_total(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not match grand total');
        $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 3.00, // less than 4.50
            ]],
        ]);
    }

    public function test_close_shift_expected_cash_includes_cash_payments(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);

        $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 9.00,
                'tendered' => 9.00,
            ]],
        ]);

        // expected_cash = 100 opening + 9 cash = 109. Counted 109 -> closed.
        $closed = $this->shifts->closeShift($shift->fresh(), 109.0);
        $this->assertSame(PosShift::STATUS_CLOSED, $closed->status);
        $this->assertEquals(109.0, (float) $closed->expected_cash);
        $this->assertEquals(0.0, (float) $closed->variance);
    }

    public function test_card_payment_does_not_affect_expected_cash(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);

        $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CARD,
                'amount' => 4.50,
                'reference_number' => 'auth_xyz',
            ]],
        ]);

        // Cash drawer untouched -> expected stays at opening float.
        $closed = $this->shifts->closeShift($shift->fresh(), 100.0);
        $this->assertSame(PosShift::STATUS_CLOSED, $closed->status);
        $this->assertEquals(100.0, (float) $closed->expected_cash);
    }

    public function test_checkout_refused_on_closed_shift(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        $this->shifts->closeShift($shift->fresh(), 100.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Cannot check out");
        $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 4.50,
                'tendered' => 5.00,
            ]],
        ]);
    }
}
