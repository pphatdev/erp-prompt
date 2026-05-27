<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\LowStockAlert;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\ProcurementService;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use App\Tenants\Modules\Inventory\Services\StockService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\TenantTestCase;

/**
 * End-to-end inventory flow tests + serialised-access concurrency surrogates.
 *
 * Two clusters:
 *
 *  1. E2E PO → GRN — exercises Procure-to-Pay through receiving and
 *     asserts that WAC, total_quantity, and downstream low-stock alerts
 *     all line up after the full pipeline runs.
 *
 *  2. Concurrency surrogates — PHPUnit can't actually fork, but every
 *     mutating path in the inventory services wraps Product /
 *     StockReservation rows in lockForUpdate. These tests run a tight
 *     loop in a single process to verify that the *business* invariants
 *     (no oversell, no double-commit, no double-expire) hold even under
 *     rapid re-entry.
 */
class InventoryE2ETest extends TenantTestCase
{
    private ProcurementService $procurement;
    private StockReservationService $reservations;
    private StockService $stock;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::login($this->admin);
        $this->procurement  = app(ProcurementService::class);
        $this->reservations = app(StockReservationService::class);
        $this->stock        = app(StockService::class);

        $this->supplier  = Supplier::create(['code' => 'SUP-E2E', 'name' => 'E2E Vendor']);
        $this->warehouse = Warehouse::create(['code' => 'WH-E2E', 'name' => 'E2E Warehouse']);
        $this->product   = Product::create([
            'sku' => 'E2E-1', 'name' => 'E2E Widget',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 12.00, 'minimum_stock_level' => 5,
        ]);
    }

    // -------------------------------------------------------------- E2E --

    public function test_po_through_grn_updates_stock_wac_and_clears_low_stock(): void
    {
        // 1) Start from zero stock — product is below threshold (5), so the
        //    next out-movement won't crash but no alert can exist yet (we
        //    haven't *crossed* downward, we were never above).
        $this->assertEquals(0.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // 2) Create draft PO, submit, approve.
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $this->product->id, 'ordered_qty' => 20, 'unit_cost' => 5.00],
            ],
        ]);
        $po = $this->procurement->submit($po);
        $po = $this->procurement->approve($po);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->status);

        // 3) Partial receipt — 8 units. PO flips to receiving.
        $line = $po->items->first();
        $po = $this->procurement->receive($po, [$line->id => 8]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVING, $po->status);
        $this->assertEquals(8.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        $product = $this->product->fresh();
        $this->assertEquals(8.0, (float) $product->total_quantity);
        $this->assertEquals(5.0, (float) $product->average_cost);  // First receipt anchors WAC to unit_cost.

        // 4) Receive the remaining 12 — PO terminal-receives.
        $po = $this->procurement->receive($po, [$line->fresh()->id => 12]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertEquals(20.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));

        // 5) WAC still 5 (no cost change between batches at same unit_cost).
        $product = $this->product->fresh();
        $this->assertEquals(20.0, (float) $product->total_quantity);
        $this->assertEquals(5.0,  (float) $product->average_cost);
        $this->assertEquals(5.0,  (float) $product->last_cost);

        // 6) Now consume below threshold (5) — alert must fire.
        $this->stock->recordMovement([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => 'out',
            'quantity'     => 17,  // 20 → 3, crosses below 5
        ]);

        $alerts = LowStockAlert::where('product_id', $this->product->id)->get();
        $this->assertCount(1, $alerts);
        $this->assertSame(LowStockAlert::STATUS_OPEN, $alerts->first()->status);
        $this->assertEquals(3.0, (float) $alerts->first()->quantity_at_alert);
    }

    public function test_po_receipts_with_different_unit_costs_blend_wac(): void
    {
        // First receipt: 10 @ 4.00 → avg 4
        $po1 = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 10, 'unit_cost' => 4.00]],
        ]);
        $po1 = $this->procurement->approve($this->procurement->submit($po1));
        $this->procurement->receive($po1, [$po1->items->first()->id => 10]);

        // Second receipt: 10 @ 6.00 → blended avg = (10*4 + 10*6) / 20 = 5.00
        $po2 = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 10, 'unit_cost' => 6.00]],
        ]);
        $po2 = $this->procurement->approve($this->procurement->submit($po2));
        $this->procurement->receive($po2, [$po2->items->first()->id => 10]);

        $p = $this->product->fresh();
        $this->assertEquals(20.0, (float) $p->total_quantity);
        $this->assertEquals(5.0,  (float) $p->average_cost);
        $this->assertEquals(6.0,  (float) $p->last_cost);
    }

    // ---------------------------------------------- Concurrency surrogates --

    public function test_reservations_cannot_oversell_under_rapid_loop(): void
    {
        // Seed 10 units, then try to reserve 4 + 4 + 4 — the third must fail.
        $this->stock->recordMovement([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => 'in',
            'quantity'     => 10,
            'unit_cost'    => 1,
        ]);

        $first  = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
        ]);
        $second = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
        ]);

        $this->assertSame(StockReservation::STATUS_ACTIVE, $first->status);
        $this->assertSame(StockReservation::STATUS_ACTIVE, $second->status);
        $this->assertEquals(2.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));

        $this->expectException(DomainException::class);
        $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
        ]);
    }

    public function test_double_commit_is_idempotent(): void
    {
        $this->stock->recordMovement([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => 'in',
            'quantity'     => 5,
            'unit_cost'    => 1,
        ]);
        $r = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3,
        ]);

        $movementsBefore = StockMovement::count();
        $this->reservations->commit($r);
        $movementsAfterFirst = StockMovement::count();
        $this->reservations->commit($r->fresh());  // re-entry
        $movementsAfterSecond = StockMovement::count();

        $this->assertSame($movementsBefore + 1, $movementsAfterFirst, 'First commit should post exactly one out movement.');
        $this->assertSame($movementsAfterFirst, $movementsAfterSecond, 'Second commit must not duplicate the out movement.');
        $this->assertSame(StockReservation::STATUS_COMMITTED, $r->fresh()->status);
    }

    public function test_expire_due_does_not_double_count(): void
    {
        $this->stock->recordMovement([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => 'in',
            'quantity'     => 5,
            'unit_cost'    => 1,
        ]);
        $r = $this->reservations->reserve(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 2],
            ttlMinutes: 1
        );
        // Backdate so it's due.
        $r->update(['expires_at' => now()->subMinute()]);

        $firstPass  = $this->reservations->expireDue();
        $secondPass = $this->reservations->expireDue();

        $this->assertSame(1, $firstPass, 'Exactly one reservation should expire on the first pass.');
        $this->assertSame(0, $secondPass, 'The second pass must not re-expire the same row.');
        $this->assertSame(StockReservation::STATUS_EXPIRED, $r->fresh()->status);
    }
}
