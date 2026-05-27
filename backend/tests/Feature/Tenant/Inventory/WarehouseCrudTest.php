<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\WarehouseService;
use Tests\Feature\TenantTestCase;

class WarehouseCrudTest extends TenantTestCase
{
    private WarehouseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarehouseService::class);
    }

    public function test_create_sets_active_by_default(): void
    {
        $w = $this->service->create([
            'code' => 'WH-MAIN', 'name' => 'Main', 'city' => 'Phnom Penh', 'country' => 'KH', 'capacity' => 500,
        ]);
        $this->assertTrue($w->isActive());
        $this->assertSame(500, (int) $w->capacity);
    }

    public function test_duplicate_code_rejected(): void
    {
        $this->service->create(['code' => 'WH-DUP', 'name' => 'A']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("code 'WH-DUP' already exists");
        $this->service->create(['code' => 'WH-DUP', 'name' => 'B']);
    }

    public function test_archive_blocked_when_stock_on_hand(): void
    {
        $w = $this->service->create(['code' => 'WH-FULL', 'name' => 'Full']);
        $p = Product::create([
            'sku' => 'P1', 'name' => 'P', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);
        StockMovement::create([
            'product_id' => $p->id, 'warehouse_id' => $w->id,
            'type' => 'in', 'quantity' => 5,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('still holds 5 unit(s)');
        $this->service->archive($w);
    }

    public function test_archive_succeeds_when_net_stock_is_zero(): void
    {
        $w = $this->service->create(['code' => 'WH-EMPTY', 'name' => 'Empty']);
        $p = Product::create([
            'sku' => 'P2', 'name' => 'P2', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);
        // 5 in, 5 out → net zero on this warehouse → archive should succeed.
        StockMovement::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'type' => 'in', 'quantity' => 5]);
        StockMovement::create(['product_id' => $p->id, 'warehouse_id' => $w->id, 'type' => 'out', 'quantity' => -5]);

        $this->service->archive($w);
        $this->assertNotNull($w->fresh()->deleted_at);
    }

    public function test_stock_by_product_returns_only_non_zero(): void
    {
        $w = $this->service->create(['code' => 'WH-X', 'name' => 'X']);
        $p1 = Product::create(['sku' => 'A', 'name' => 'A', 'product_type' => Product::TYPE_HARDWARE, 'unit_price' => 1, 'minimum_stock_level' => 0]);
        $p2 = Product::create(['sku' => 'B', 'name' => 'B', 'product_type' => Product::TYPE_HARDWARE, 'unit_price' => 1, 'minimum_stock_level' => 0]);

        StockMovement::create(['product_id' => $p1->id, 'warehouse_id' => $w->id, 'type' => 'in',  'quantity' => 10]);
        StockMovement::create(['product_id' => $p2->id, 'warehouse_id' => $w->id, 'type' => 'in',  'quantity' => 3]);
        StockMovement::create(['product_id' => $p2->id, 'warehouse_id' => $w->id, 'type' => 'out', 'quantity' => -3]);

        $rows = $this->service->stockByProduct($w);
        $this->assertCount(1, $rows, 'Zero-net product B must be excluded.');
        $this->assertSame($p1->id, $rows[0]['productId']);
        $this->assertSame(10, $rows[0]['onHand']);
    }
}
