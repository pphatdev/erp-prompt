<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\Inventory\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\TenantTestCase;

class StockTransferTest extends TenantTestCase
{
    private StockTransferService $svc;
    private StockService $stock;
    private Warehouse $a;
    private Warehouse $b;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::login($this->admin);
        $this->svc   = app(StockTransferService::class);
        $this->stock = app(StockService::class);
        $this->a = Warehouse::create(['code' => 'A', 'name' => 'A WH']);
        $this->b = Warehouse::create(['code' => 'B', 'name' => 'B WH']);
        $this->product = Product::create([
            'sku' => 'T-1', 'name' => 'Transfer Widget',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);
        // Seed 50 at source.
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->a->id,
            'type' => 'in', 'quantity' => 50, 'unit_cost' => 4,
        ]);
    }

    public function test_full_lifecycle_dispatch_then_receive(): void
    {
        $t = $this->svc->create([
            'from_warehouse_id' => $this->a->id,
            'to_warehouse_id'   => $this->b->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 20]],
        ]);
        $this->assertSame(StockTransfer::STATUS_DRAFT, $t->status);
        $this->assertEquals(50.0, $this->stock->getPhysicalStock($this->product->id, $this->a->id));

        $t = $this->svc->dispatch($t);
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $t->status);
        $this->assertEquals(30.0, $this->stock->getPhysicalStock($this->product->id, $this->a->id));
        $this->assertEquals(0.0,  $this->stock->getPhysicalStock($this->product->id, $this->b->id));

        $t = $this->svc->receive($t);
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $t->status);
        $this->assertEquals(30.0, $this->stock->getPhysicalStock($this->product->id, $this->a->id));
        $this->assertEquals(20.0, $this->stock->getPhysicalStock($this->product->id, $this->b->id));
    }

    public function test_dispatch_fails_when_source_is_short(): void
    {
        $t = $this->svc->create([
            'from_warehouse_id' => $this->a->id,
            'to_warehouse_id'   => $this->b->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 999]],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock');
        $this->svc->dispatch($t);
    }

    public function test_partial_receive_keeps_in_transit(): void
    {
        $t = $this->svc->create([
            'from_warehouse_id' => $this->a->id,
            'to_warehouse_id'   => $this->b->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 20]],
        ]);
        $t = $this->svc->dispatch($t);
        $itemId = $t->items->first()->id;

        $t = $this->svc->receive($t, [$itemId => 12]);

        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $t->status);
        $this->assertEquals(12.0, (float) $t->items->first()->received_qty);
        $this->assertEquals(12.0, $this->stock->getPhysicalStock($this->product->id, $this->b->id));
    }

    public function test_cancel_in_transit_reverses_unreceived_units(): void
    {
        $t = $this->svc->create([
            'from_warehouse_id' => $this->a->id,
            'to_warehouse_id'   => $this->b->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 20]],
        ]);
        $t = $this->svc->dispatch($t);
        // Source dropped to 30, destination 0, transfer in_transit holding 20.

        $t = $this->svc->cancel($t, 'Truck broke down');

        $this->assertSame(StockTransfer::STATUS_CANCELLED, $t->status);
        // 20 unreceived units credited back to source → restored to 50.
        $this->assertEquals(50.0, $this->stock->getPhysicalStock($this->product->id, $this->a->id));
        $this->assertEquals(0.0,  $this->stock->getPhysicalStock($this->product->id, $this->b->id));
    }

    public function test_cannot_cancel_received_transfer(): void
    {
        $t = $this->svc->create([
            'from_warehouse_id' => $this->a->id,
            'to_warehouse_id'   => $this->b->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 20]],
        ]);
        $t = $this->svc->dispatch($t);
        $t = $this->svc->receive($t);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel a fully received transfer');
        $this->svc->cancel($t);
    }
}
