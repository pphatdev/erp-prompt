<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\LowStockAlert;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Events\ProductWentBelowMinimumStock;
use App\Tenants\Modules\Inventory\Services\StockService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\TenantTestCase;

class LowStockAlertTest extends TenantTestCase
{
    private StockService $stock;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->warehouse = Warehouse::create(['code' => 'WH-LOW', 'name' => 'Low Stock WH']);
        $this->product = Product::create([
            'sku' => 'LOW-1', 'name' => 'Test Widget',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 10,
        ]);
        // Seed 20 units so first crossing-down can happen.
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 20, 'unit_cost' => 5,
        ]);
    }

    public function test_event_fires_when_quantity_crosses_threshold_downward(): void
    {
        Event::fake([ProductWentBelowMinimumStock::class]);

        // 20 → 8 crosses below 10.
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 12,
        ]);

        Event::assertDispatched(ProductWentBelowMinimumStock::class, function ($e) {
            return $e->product->id === $this->product->id
                && $e->previousQuantity == 20
                && $e->currentQuantity == 8
                && $e->threshold === 10;
        });
    }

    public function test_event_does_not_fire_while_already_below_threshold(): void
    {
        // First move: 20 → 5 (crosses). Then second move: 5 → 2 (already below).
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 15,
        ]);

        Event::fake([ProductWentBelowMinimumStock::class]);

        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 3,
        ]);

        Event::assertNotDispatched(ProductWentBelowMinimumStock::class);
    }

    public function test_listener_creates_alert_idempotently(): void
    {
        // 20 → 5
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 15,
        ]);

        $this->assertSame(1, LowStockAlert::where('product_id', $this->product->id)->count());
        $alert = LowStockAlert::where('product_id', $this->product->id)->first();
        $this->assertSame(LowStockAlert::STATUS_OPEN, $alert->status);
        $this->assertEquals(10, $alert->threshold);
        $this->assertEquals(5, (float) $alert->quantity_at_alert);

        // Manually re-fire to confirm listener doesn't double-write while the
        // existing alert is still open.
        event(new ProductWentBelowMinimumStock($this->product->fresh(), 5, 4, 10));

        $this->assertSame(1, LowStockAlert::where('product_id', $this->product->id)->count());
    }

    public function test_event_does_not_fire_for_products_without_threshold(): void
    {
        Event::fake([ProductWentBelowMinimumStock::class]);

        $noThreshold = Product::create([
            'sku' => 'NO-THR', 'name' => 'Untracked', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 0, 'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $noThreshold->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 100, 'unit_cost' => 1,
        ]);
        $this->stock->recordMovement([
            'product_id' => $noThreshold->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'out', 'quantity' => 99,
        ]);

        Event::assertNotDispatched(ProductWentBelowMinimumStock::class);
    }
}
