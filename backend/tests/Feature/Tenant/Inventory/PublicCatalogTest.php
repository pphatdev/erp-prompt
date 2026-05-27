<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

class PublicCatalogTest extends TenantTestCase
{
    private function publicGet(string $uri)
    {
        // No actingAs — the storefront is intentionally unauthenticated.
        return $this->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson($uri);
    }

    public function test_index_lists_active_products_only(): void
    {
        Product::create([
            'sku' => 'A', 'name' => 'Alpha', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0, 'is_active' => true,
        ]);
        Product::create([
            'sku' => 'B', 'name' => 'Bravo (inactive)', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 99, 'minimum_stock_level' => 0, 'is_active' => false,
        ]);

        $res = $this->publicGet('/public/catalog');
        $res->assertOk();
        $skus = collect($res->json('data'))->pluck('sku')->all();
        $this->assertContains('A', $skus);
        $this->assertNotContains('B', $skus);
    }

    public function test_index_does_not_leak_cost_fields(): void
    {
        Product::create([
            'sku' => 'C', 'name' => 'Cost-bearing', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 50, 'minimum_stock_level' => 0, 'is_active' => true,
            'average_cost' => 30, 'last_cost' => 28,
        ]);

        $row = collect($this->publicGet('/public/catalog')->json('data'))
            ->firstWhere('sku', 'C');

        $this->assertArrayNotHasKey('averageCost', $row);
        $this->assertArrayNotHasKey('lastCost', $row);
        $this->assertArrayNotHasKey('totalQuantity', $row);
        $this->assertSame(50.0, $row['unitPrice']);
    }

    public function test_show_returns_404_for_inactive_product(): void
    {
        $p = Product::create([
            'sku' => 'D', 'name' => 'Dormant', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 1, 'minimum_stock_level' => 0, 'is_active' => false,
        ]);
        $this->publicGet("/public/catalog/{$p->id}")->assertNotFound();
    }

    public function test_availability_returns_per_warehouse_breakdown(): void
    {
        $p = Product::create([
            'sku' => 'E', 'name' => 'Edge', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 5, 'minimum_stock_level' => 0, 'is_active' => true,
        ]);
        $w1 = Warehouse::create(['code' => 'W1', 'name' => 'WH One']);
        $w2 = Warehouse::create(['code' => 'W2', 'name' => 'WH Two']);
        $w3 = Warehouse::create(['code' => 'W3', 'name' => 'Empty']);

        $stock = app(StockService::class);
        $stock->recordMovement(['product_id' => $p->id, 'warehouse_id' => $w1->id, 'type' => 'in', 'quantity' => 7,  'unit_cost' => 1]);
        $stock->recordMovement(['product_id' => $p->id, 'warehouse_id' => $w2->id, 'type' => 'in', 'quantity' => 12, 'unit_cost' => 1]);

        $res = $this->publicGet("/public/catalog/{$p->id}/availability");
        $res->assertOk();

        $codes = collect($res->json('warehouseBreakdown'))->pluck('warehouseCode')->all();
        $this->assertContains('W1', $codes);
        $this->assertContains('W2', $codes);
        // W3 is empty — must be filtered out so a storefront doesn't display "0 available".
        $this->assertNotContains('W3', $codes);
        $this->assertSame(19.0, (float) $res->json('totalAvailable'));
    }
}
