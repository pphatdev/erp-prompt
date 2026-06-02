<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo hardware product catalogue.
 *
 * Seeds 3 categories (Laptops, Phones, Accessories) and 12 active hardware
 * products, then anchors initial stock via StockService::recordMovement so
 * `products.total_quantity`, `average_cost`, and `last_cost` are populated
 * correctly. POS can ring these immediately after seeding.
 *
 * Run per-tenant (AFTER DemoVendorsWarehousesSeeder):
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\DemoHardwareProductsSeeder" --option="force=true"
 *
 * Idempotency:
 *  - Categories upserted by slug.
 *  - Products upserted by SKU. Stock-in movements are gated on a
 *    product-level marker so re-runs don't double-anchor inventory.
 */
class DemoHardwareProductsSeeder extends Seeder
{
    public function run(): void
    {
        if (!tenant()?->getTenantKey()) {
            $this->command?->warn('DemoHardwareProductsSeeder skipped - no tenant context. Use `php artisan tenants:run db:seed`.');
            return;
        }
        if (!Schema::hasTable('products')) {
            return;
        }

        $categoryIds = $this->seedCategories();
        $warehouse = Warehouse::where('code', 'MAIN')->first()
            ?? Warehouse::orderBy('code')->first();
        if (!$warehouse) {
            $this->command?->warn('No warehouses found. Run DemoVendorsWarehousesSeeder first (or DemoTenantSeeder which chains them).');
            return;
        }

        $rows = $this->productCatalogue();
        $stock = app(StockService::class);

        foreach ($rows as $row) {
            $stockIn = $row['_stock_in'] ?? 0;
            $unitCost = $row['_unit_cost'] ?? null;
            unset($row['_stock_in'], $row['_unit_cost']);

            // Map category by slug -> id.
            if (isset($row['_category_slug'])) {
                $row['category_id'] = $categoryIds[$row['_category_slug']] ?? null;
                unset($row['_category_slug']);
            }

            $product = Product::updateOrCreate(['sku' => $row['sku']], $row);

            // Stock anchor: only if the product has zero on-hand AND we
            // have a warehouse + a positive stock-in plan. This makes
            // re-runs idempotent without a per-tenant tracking table.
            if ($stockIn > 0 && (float) $product->total_quantity === 0.0) {
                $stock->recordMovement([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'in',
                    'quantity' => $stockIn,
                    'unit_cost' => $unitCost,
                    'reference' => 'DEMO-SEED',
                    'notes' => 'Demo catalogue anchor stock-in.',
                ]);
            }
        }

        $catCount = count($categoryIds);
        $prodCount = Product::count();
        $this->command?->info("Demo catalogue seeded ({$catCount} categories, {$prodCount} products).");
    }

    /** @return array<string, string>  slug -> category id */
    private function seedCategories(): array
    {
        if (!Schema::hasTable('categories')) {
            return [];
        }

        $catalog = [
            ['slug' => 'laptops', 'name' => 'Laptops', 'color' => '#3B82F6'],
            ['slug' => 'phones', 'name' => 'Phones & Tablets', 'color' => '#10B981'],
            ['slug' => 'accessories', 'name' => 'Accessories', 'color' => '#F59E0B'],
        ];

        $out = [];
        foreach ($catalog as $row) {
            $cat = Category::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'color' => $row['color'],
                    'is_active' => true,
                ]
            );
            $out[$row['slug']] = $cat->id;
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>  product rows with
     *      `_category_slug`, `_stock_in`, `_unit_cost` meta keys stripped
     *      before insertion.
     */
    private function productCatalogue(): array
    {
        return [
            // ───── Laptops ─────
            [
                'sku' => 'NB-ASUS-VB14',
                'name' => 'ASUS VivoBook 14 (i5/8GB/512GB)',
                'product_type' => 'hardware',
                'description' => '14" FHD laptop, Intel Core i5, 8GB RAM, 512GB SSD.',
                'unit_price' => 899.00,
                'minimum_stock_level' => 5,
                'is_active' => true,
                '_category_slug' => 'laptops',
                '_stock_in' => 8,
                '_unit_cost' => 720.00,
            ],
            [
                'sku' => 'NB-ASUS-ZB13',
                'name' => 'ASUS ZenBook 13 OLED (i7/16GB/1TB)',
                'product_type' => 'hardware',
                'description' => '13" OLED ultrabook with Intel Core i7 and 16GB RAM.',
                'unit_price' => 1399.00,
                'minimum_stock_level' => 3,
                'is_active' => true,
                '_category_slug' => 'laptops',
                '_stock_in' => 4,
                '_unit_cost' => 1180.00,
            ],
            [
                'sku' => 'NB-HP-PV15',
                'name' => 'HP Pavilion 15 (Ryzen 5/16GB/512GB)',
                'product_type' => 'hardware',
                'description' => '15.6" laptop, AMD Ryzen 5, 16GB RAM, 512GB SSD.',
                'unit_price' => 779.00,
                'minimum_stock_level' => 5,
                'is_active' => true,
                '_category_slug' => 'laptops',
                '_stock_in' => 6,
                '_unit_cost' => 640.00,
            ],
            [
                'sku' => 'NB-LEN-T14',
                'name' => 'Lenovo ThinkPad T14 (i7/32GB/1TB)',
                'product_type' => 'hardware',
                'description' => 'Business-grade 14" ThinkPad with vPro, MIL-STD chassis.',
                'unit_price' => 1599.00,
                'minimum_stock_level' => 3,
                'is_active' => true,
                '_category_slug' => 'laptops',
                '_stock_in' => 3,
                '_unit_cost' => 1340.00,
            ],

            // ───── Phones & Tablets ─────
            [
                'sku' => 'PH-APL-IP15',
                'name' => 'Apple iPhone 15 (128GB, Black)',
                'product_type' => 'hardware',
                'description' => 'iPhone 15 with USB-C and 48MP main camera.',
                'unit_price' => 899.00,
                'minimum_stock_level' => 5,
                'is_active' => true,
                '_category_slug' => 'phones',
                '_stock_in' => 10,
                '_unit_cost' => 770.00,
            ],
            [
                'sku' => 'PH-SAM-S24',
                'name' => 'Samsung Galaxy S24 (256GB)',
                'product_type' => 'hardware',
                'description' => 'Samsung flagship with Snapdragon 8 Gen 3.',
                'unit_price' => 849.00,
                'minimum_stock_level' => 5,
                'is_active' => true,
                '_category_slug' => 'phones',
                '_stock_in' => 8,
                '_unit_cost' => 720.00,
            ],
            [
                'sku' => 'PH-XIA-RD13',
                'name' => 'Xiaomi Redmi Note 13 Pro (256GB)',
                'product_type' => 'hardware',
                'description' => 'Mid-range Redmi with 200MP camera and 67W charging.',
                'unit_price' => 329.00,
                'minimum_stock_level' => 10,
                'is_active' => true,
                '_category_slug' => 'phones',
                '_stock_in' => 20,
                '_unit_cost' => 268.00,
            ],
            [
                'sku' => 'TB-APL-IPA-11',
                'name' => 'Apple iPad Air 11 (M2, 128GB Wi-Fi)',
                'product_type' => 'hardware',
                'description' => 'iPad Air with the M2 chip and 11" Liquid Retina display.',
                'unit_price' => 599.00,
                'minimum_stock_level' => 4,
                'is_active' => true,
                '_category_slug' => 'phones',
                '_stock_in' => 6,
                '_unit_cost' => 510.00,
            ],

            // ───── Accessories ─────
            [
                'sku' => 'AC-LOG-MX3',
                'name' => 'Logitech MX Master 3S Wireless Mouse',
                'product_type' => 'hardware',
                'description' => 'Premium wireless productivity mouse, quiet click.',
                'unit_price' => 109.00,
                'minimum_stock_level' => 10,
                'is_active' => true,
                '_category_slug' => 'accessories',
                '_stock_in' => 25,
                '_unit_cost' => 78.00,
            ],
            [
                'sku' => 'AC-LOG-MX-KB',
                'name' => 'Logitech MX Keys Wireless Keyboard',
                'product_type' => 'hardware',
                'description' => 'Backlit, low-profile wireless keyboard with USB-C.',
                'unit_price' => 129.00,
                'minimum_stock_level' => 10,
                'is_active' => true,
                '_category_slug' => 'accessories',
                '_stock_in' => 20,
                '_unit_cost' => 92.00,
            ],
            [
                'sku' => 'AC-ANK-USC-65',
                'name' => 'Anker 65W USB-C GaN Charger',
                'product_type' => 'hardware',
                'description' => 'Compact 65W GaN charger, 3 ports (2x USB-C, 1x USB-A).',
                'unit_price' => 49.00,
                'minimum_stock_level' => 20,
                'is_active' => true,
                '_category_slug' => 'accessories',
                '_stock_in' => 40,
                '_unit_cost' => 32.00,
            ],
            [
                'sku' => 'AC-SAM-T7-1T',
                'name' => 'Samsung T7 Portable SSD 1TB',
                'product_type' => 'hardware',
                'description' => 'USB 3.2 portable SSD with up to 1,050 MB/s reads.',
                'unit_price' => 119.00,
                'minimum_stock_level' => 10,
                'is_active' => true,
                '_category_slug' => 'accessories',
                '_stock_in' => 15,
                '_unit_cost' => 86.00,
            ],
        ];
    }
}
