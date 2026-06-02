<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\POS;

use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosPayment;
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
 * P1 - voidOrder restocks every line, reverses the original journal, and
 * is a no-op on a second call. Non-paid orders cannot be voided.
 */
class PosVoidLifecycleTest extends TenantTestCase
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

        $this->warehouse = Warehouse::create(['code' => 'WH-VD', 'name' => 'Void WH']);
        $this->terminal = PosTerminal::create([
            'code' => 'REG-VD',
            'name' => 'Void Register',
            'warehouse_id' => $this->warehouse->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $this->cashier = User::create([
            'name' => 'VD Cashier', 'email' => 'vd@test.com', 'password' => 'secret123',
        ]);
        $this->product = Product::create([
            'sku' => 'VD-1', 'name' => 'Bagel',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 3.00, 'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 10, 'unit_cost' => 1.00,
        ]);
    }

    private function placePaidOrder(int $qty = 2): PosOrder
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        return $this->orders->checkout($shift->fresh(), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $qty,
            ]],
            'payments' => [[
                'payment_method' => PosPayment::METHOD_CASH,
                'amount' => 3.00 * $qty,
                'tendered' => 3.00 * $qty,
            ]],
        ]);
    }

    public function test_void_restocks_reverses_journal_and_flips_to_voided(): void
    {
        $order = $this->placePaidOrder(2);
        $this->assertSame(8.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        $originalJournalId = $order->journal_entry_id;
        $this->assertNotNull($originalJournalId);

        $voided = $this->orders->voidOrder($order, 'wrong product');

        $this->assertSame(PosOrder::STATUS_VOIDED, $voided->status);
        $this->assertSame('wrong product', $voided->void_reason);
        $this->assertNotNull($voided->void_journal_entry_id);

        // Stock restocked to original.
        $this->assertSame(10.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // Compensating in-movement is referenced as POS-VOID.
        $in = StockMovement::where('reference', "POS-VOID:{$order->order_number}")
            ->where('type', 'in')
            ->first();
        $this->assertNotNull($in);

        // Reversing journal entry points back at the original.
        $reversal = JournalEntry::where('reverses_journal_entry_id', $originalJournalId)->first();
        $this->assertNotNull($reversal);
        $this->assertSame($voided->void_journal_entry_id, $reversal->id);
    }

    public function test_double_void_is_idempotent_noop(): void
    {
        $order = $this->placePaidOrder(1);
        $first = $this->orders->voidOrder($order, 'reason');
        $beforeStock = $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id);

        $second = $this->orders->voidOrder($first->fresh(), 'reason again');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(PosOrder::STATUS_VOIDED, $second->status);
        // No second restock + no second reversing journal.
        $this->assertSame($beforeStock, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));
        $this->assertSame(1, StockMovement::where('reference', "POS-VOID:{$order->order_number}")->where('type', 'in')->count());
    }

    public function test_cannot_void_a_refunded_or_non_paid_order(): void
    {
        $order = $this->placePaidOrder(1);
        // Manually flip to a non-paid status that isn't voided.
        $order->update(['status' => PosOrder::STATUS_REFUNDED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only paid orders can be voided');
        $this->orders->voidOrder($order->fresh(), 'reason');
    }
}
