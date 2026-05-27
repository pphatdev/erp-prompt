<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\ProcurementService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

class WeightedAverageCostingTest extends TenantTestCase
{
    private StockService $stock;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->warehouse = Warehouse::create(['code' => 'WH-WAC', 'name' => 'WAC Warehouse']);
        $this->product = Product::create([
            'sku' => 'WAC-1', 'name' => 'WAC widget',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 0, 'minimum_stock_level' => 0,
        ]);
    }

    public function test_first_receipt_anchors_average_to_unit_cost(): void
    {
        // Starts at qty=0, avg=0. First in: 10 units @ $5 → avg should be $5.
        $this->stock->recordMovement([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => 'in',
            'quantity'     => 10,
            'unit_cost'    => 5.00,
        ]);

        $this->product->refresh();
        $this->assertSame(10.0, (float) $this->product->total_quantity);
        $this->assertEqualsWithDelta(5.0000, (float) $this->product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(5.0000, (float) $this->product->last_cost, 0.0001);
    }

    public function test_cumulative_averaging_across_three_receipts(): void
    {
        // R1: 10 @ $5  → qty=10, avg=$5
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 10, 'unit_cost' => 5.00,
        ]);
        // R2: 5  @ $7  → qty=15, avg = (10*5 + 5*7)/15 = 85/15 = 5.6667
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 5, 'unit_cost' => 7.00,
        ]);
        // R3: 25 @ $6  → qty=40, avg = (15*5.6667 + 25*6)/40 = (85 + 150)/40 = 5.875
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 25, 'unit_cost' => 6.00,
        ]);

        $this->product->refresh();
        $this->assertSame(40.0, (float) $this->product->total_quantity);
        $this->assertEqualsWithDelta(5.8750, (float) $this->product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(6.0000, (float) $this->product->last_cost, 0.0001);
    }

    public function test_out_movement_drops_quantity_but_keeps_average(): void
    {
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 10, 'unit_cost' => 8.00,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 3,
        ]);

        $this->product->refresh();
        $this->assertSame(7.0, (float) $this->product->total_quantity);
        // average_cost MUST stay at $8 — out-movements don't affect cost basis.
        $this->assertEqualsWithDelta(8.0000, (float) $this->product->average_cost, 0.0001);
    }

    public function test_receipt_without_unit_cost_updates_quantity_only(): void
    {
        // Legacy callers that don't pass unit_cost should still bump qty but
        // leave the cost basis untouched (don't poison WAC with $0).
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 10, 'unit_cost' => 4.00,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 5, // no unit_cost
        ]);

        $this->product->refresh();
        $this->assertSame(15.0, (float) $this->product->total_quantity);
        $this->assertEqualsWithDelta(4.0000, (float) $this->product->average_cost, 0.0001);
    }

    public function test_procurement_receipt_feeds_wac_from_po_line_cost(): void
    {
        $supplier = Supplier::create(['code' => 'SUP-WAC', 'name' => 'WAC Supplier']);
        $proc = app(ProcurementService::class);

        $po = $proc->createDraft([
            'supplier_id'  => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 8, 'unit_cost' => 12.50]],
        ]);
        $po = $proc->approve($proc->submit($po));
        $line = $po->items->first();
        $proc->receive($po, [$line->id => 8]);

        $this->product->refresh();
        $this->assertSame(8.0, (float) $this->product->total_quantity);
        $this->assertEqualsWithDelta(12.5000, (float) $this->product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(12.5000, (float) $this->product->last_cost, 0.0001);
    }
}
