<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\ProcurementService;
use App\Tenants\Modules\Inventory\Services\SupplierService;
use Tests\Feature\TenantTestCase;

class ProcurementLifecycleTest extends TenantTestCase
{
    private ProcurementService $procurement;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurement = app(ProcurementService::class);

        $this->supplier  = Supplier::create(['code' => 'SUP-01', 'name' => 'Acme Supplies']);
        $this->warehouse = Warehouse::create(['code' => 'WH-01', 'name' => 'Main']);
        $this->productA  = Product::create([
            'sku' => 'A', 'name' => 'Widget A', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);
        $this->productB = Product::create([
            'sku' => 'B', 'name' => 'Widget B', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 25, 'minimum_stock_level' => 0,
        ]);
    }

    public function test_create_draft_snapshots_lines_and_recalculates_totals(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $this->productA->id, 'ordered_qty' => 3, 'unit_cost' => 8],
                ['product_id' => $this->productB->id, 'ordered_qty' => 2],
            ],
        ]);

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertSame(2, $po->items->count());
        // line totals: 3 * 8 + 2 * 25 = 74
        $this->assertEqualsWithDelta(74.0, (float) $po->total_amount, 0.01);
        $this->assertNotEmpty($po->po_number);
    }

    public function test_full_lifecycle_draft_submit_approve_full_receive_posts_stock(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 5, 'unit_cost' => 10]],
        ]);

        $po = $this->procurement->submit($po);
        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $po->status);

        $po = $this->procurement->approve($po);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->status);

        $line = $po->items->first();
        $po = $this->procurement->receive($po, [$line->id => 5]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertNotNull($po->received_at);

        // Stock posted via StockService → one stock_movement row with qty=5.
        $totalIn = StockMovement::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'in')
            ->sum('quantity');
        $this->assertSame(5, (int) $totalIn);
    }

    public function test_partial_receipt_flips_to_receiving_then_received_when_complete(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 10, 'unit_cost' => 10]],
        ]);
        $po = $this->procurement->approve($this->procurement->submit($po));

        $line = $po->items->first();
        $po = $this->procurement->receive($po, [$line->id => 4]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVING, $po->status);
        $this->assertSame(4.0, (float) $po->items->first()->received_qty);

        // Second partial completes it.
        $po = $this->procurement->receive($po, [$line->id => 6]);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertSame(10.0, (float) $po->items->first()->received_qty);
    }

    public function test_over_receipt_is_rejected(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 3, 'unit_cost' => 1]],
        ]);
        $po = $this->procurement->approve($this->procurement->submit($po));
        $line = $po->items->first();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Over-receipt blocked');
        $this->procurement->receive($po, [$line->id => 5]);
    }

    public function test_cannot_cancel_after_partial_receipt(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 4, 'unit_cost' => 1]],
        ]);
        $po = $this->procurement->approve($this->procurement->submit($po));
        $line = $po->items->first();
        $this->procurement->receive($po, [$line->id => 2]); // → receiving

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel a receiving PO');
        $this->procurement->cancel($po->fresh(), 'too late');
    }

    public function test_cancel_draft_succeeds(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 1, 'unit_cost' => 1]],
        ]);
        $cancelled = $this->procurement->cancel($po, 'Test cancel');
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Test cancel', $cancelled->cancel_reason);
    }

    public function test_supplier_archive_blocked_by_open_purchase_order(): void
    {
        $po = $this->procurement->createDraft([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->productA->id, 'ordered_qty' => 1, 'unit_cost' => 1]],
        ]);
        $this->procurement->submit($po); // → submitted = open

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('1 open purchase order(s)');
        app(SupplierService::class)->archive($this->supplier->fresh());
    }
}
