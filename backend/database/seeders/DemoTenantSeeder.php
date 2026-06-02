<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-call demo data pack for a fresh tenant.
 *
 * Runs the three demo seeders in dependency order:
 *   1. DemoVendorsWarehousesSeeder  - MAIN + RETAIL warehouses, 4 suppliers
 *                                     (2 plain, 2 AP-vendor with bank details).
 *   2. DemoHardwareProductsSeeder   - 3 categories + 12 hardware products,
 *                                     each anchored with an initial stock-in
 *                                     into MAIN so POS can ring them up.
 *   3. DemoCustomersSeeder          - 3 individual + 3 business + 3 tenant
 *                                     customers (all three Customer::TYPES).
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\DemoTenantSeeder" --option="force=true"
 *
 * All three child seeders are idempotent on natural keys, so this is safe
 * to re-run.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoVendorsWarehousesSeeder::class);
        $this->call(DemoHardwareProductsSeeder::class);
        $this->call(DemoCustomersSeeder::class);
    }
}
