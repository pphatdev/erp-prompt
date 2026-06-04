<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo warehouses + suppliers (some flagged as vendors for AP).
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\DemoVendorsWarehousesSeeder" --option="force=true"
 *
 * Idempotent on warehouse `code` and supplier `code`. Re-runs update names
 * + flags but never duplicate rows. Run BEFORE the products demo seeder so
 * stock-in movements have a warehouse to land in.
 */
class DemoVendorsWarehousesSeeder extends Seeder
{
    public function run(): void
    {
        if (!tenant()?->getTenantKey()) {
            $this->command?->warn('DemoVendorsWarehousesSeeder skipped - no tenant context. Use `php artisan tenants:run db:seed`.');
            return;
        }
        $this->seedWarehouses();
        $this->seedSuppliers();

        $whCount = Warehouse::count();
        $supCount = Supplier::count();
        $this->command?->info("Demo warehouses + suppliers seeded ({$whCount} warehouses, {$supCount} suppliers).");
    }

    private function seedWarehouses(): void
    {
        if (!Schema::hasTable('warehouses')) {
            return;
        }

        $rows = [
            [
                'code' => 'MAIN',
                'name' => 'Main Warehouse',
                'location' => 'Phnom Penh',
                'address_line' => '#42, Street 271',
                'city' => 'Phnom Penh',
                'country' => 'KH',
                'capacity' => 10000,
                'is_active' => true,
                'notes' => 'Primary central warehouse - default for POS stock-outs.',
            ],
            [
                'code' => 'RETAIL',
                'name' => 'Retail Counter Stock',
                'location' => 'Phnom Penh Storefront',
                'address_line' => 'Ground floor, Storefront',
                'city' => 'Phnom Penh',
                'country' => 'KH',
                'capacity' => 2000,
                'is_active' => true,
                'notes' => 'On-premise stock for walk-in customers.',
            ],
        ];

        foreach ($rows as $row) {
            $warehouse = Warehouse::withoutGlobalScope(\Stancl\Tenancy\Database\TenantScope::class)
                ->withTrashed()
                ->where('code', $row['code'])
                ->first();

            if ($warehouse) {
                $warehouse->update(collect($row)->except('tenant_id')->toArray());
                if ($warehouse->trashed()) {
                    $warehouse->restore();
                }
            } else {
                Warehouse::create($row);
            }
        }
    }

    private function seedSuppliers(): void
    {
        if (!Schema::hasTable('suppliers')) {
            return;
        }

        $rows = [
            // Plain supplier (not flagged as vendor).
            [
                'code' => 'SUP-001',
                'name' => 'TechSource Distribution',
                'contact_name' => 'Vannak Som',
                'email' => 'orders@techsource.kh',
                'phone' => '+855 23 555 0101',
                'address' => '#88, Norodom Boulevard, Phnom Penh',
                'website' => 'https://techsource.kh',
                'tax_id' => 'L010-100100001',
                'payment_terms' => 'NET30',
                'lead_time_days' => 7,
                'rating' => 4,
                'is_active' => true,
                'is_vendor' => false,
                'notes' => 'Tier-1 hardware distributor. Bulk pricing on 50+ units.',
            ],
            [
                'code' => 'SUP-002',
                'name' => 'Mekong Mobile Imports',
                'contact_name' => 'Sopheap Chan',
                'email' => 'wholesale@mekongmobile.kh',
                'phone' => '+855 23 555 0202',
                'address' => 'Olympic Market, Phnom Penh',
                'tax_id' => 'L010-100100002',
                'payment_terms' => 'NET14',
                'lead_time_days' => 4,
                'rating' => 5,
                'is_active' => true,
                'is_vendor' => false,
                'notes' => 'Specializes in phones + tablets. Quickest lead times.',
            ],

            // Vendors (AP-bookable). Carry payment method + bank details.
            [
                'code' => 'VEN-001',
                'name' => 'ASUS Cambodia Authorized',
                'contact_name' => 'Channary Pich',
                'email' => 'ap@asus-kh.com',
                'phone' => '+855 23 555 0303',
                'address' => '#15, Russian Federation Boulevard',
                'website' => 'https://asus.com/kh',
                'tax_id' => 'L010-100100003',
                'payment_terms' => 'NET45',
                'lead_time_days' => 14,
                'rating' => 5,
                'is_active' => true,
                'is_vendor' => true,
                'payment_method' => 'bank_transfer',
                'bank_name' => 'ABA Bank',
                'bank_account_name' => 'ASUS Cambodia Authorized',
                'bank_account_number' => '000-123-456-789',
                'bank_swift' => 'ABAAKHPP',
                'notes' => 'Direct OEM. Authorized distributor for laptops + monitors.',
            ],
            [
                'code' => 'VEN-002',
                'name' => 'CoolerStock Components',
                'contact_name' => 'Ratha Long',
                'email' => 'invoices@coolerstock.kh',
                'phone' => '+855 23 555 0404',
                'address' => 'Russey Keo District, Phnom Penh',
                'tax_id' => 'L010-100100004',
                'payment_terms' => 'NET30',
                'lead_time_days' => 10,
                'rating' => 4,
                'is_active' => true,
                'is_vendor' => true,
                'payment_method' => 'bank_transfer',
                'bank_name' => 'ACLEDA Bank',
                'bank_account_name' => 'CoolerStock Components Co.',
                'bank_account_number' => '111-222-333-444',
                'bank_swift' => 'ACLBKHPP',
                'notes' => 'Accessories + peripherals: keyboards, mice, cables, chargers.',
            ],
        ];

        foreach ($rows as $row) {
            $supplier = Supplier::withoutGlobalScope(\Stancl\Tenancy\Database\TenantScope::class)
                ->withTrashed()
                ->where('code', $row['code'])
                ->first();

            if ($supplier) {
                $supplier->update(collect($row)->except('tenant_id')->toArray());
                if ($supplier->trashed()) {
                    $supplier->restore();
                }
            } else {
                Supplier::create($row);
            }
        }
    }
}
