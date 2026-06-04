<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo customers across all three Customer::TYPES:
 *   - individual : retail/walk-in style, basic identity only.
 *   - business   : B2B partner with tax_id, industry, structured billing.
 *   - tenant     : SaaS customer eligible for tenant provisioning on a
 *                  subscription confirm. Carries `tenant_handle` so the
 *                  provisioner has a destination subdomain.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\DemoCustomersSeeder" --option="force=true"
 *
 * Idempotency: keyed on `external_code` (always set), so re-runs update
 * the row in place rather than creating duplicates.
 */
class DemoCustomersSeeder extends Seeder
{
    public function run(): void
    {
        if (!tenant()?->getTenantKey()) {
            $this->command?->warn('DemoCustomersSeeder skipped - no tenant context. Use `php artisan tenants:run db:seed`.');
            return;
        }
        if (!Schema::hasTable('customers')) {
            return;
        }

        foreach ($this->rows() as $row) {
            $customer = Customer::withoutGlobalScope(\Stancl\Tenancy\Database\TenantScope::class)
                ->withTrashed()
                ->where('external_code', $row['external_code'])
                ->first();

            if ($customer) {
                $customer->update(collect($row)->except('tenant_id')->toArray());
                if ($customer->trashed()) {
                    $customer->restore();
                }
            } else {
                Customer::create($row);
            }
        }

        $count = Customer::count();
        $this->command?->info("Demo customers seeded ({$count} total across individual, business, tenant types).");
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return [
            // ───── Individual (walk-in / retail) ─────
            [
                'external_code' => 'IND-001',
                'name' => 'Sokha Chea',
                'email' => 'sokha.chea@example.com',
                'phone' => '+855 12 555 0101',
                'address' => '#21, Street 178, Phnom Penh',
                'status' => 'active',
                'customer_type' => Customer::TYPE_INDIVIDUAL,
                'tier' => Customer::TIER_STANDARD,
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'notes' => 'Walk-in customer, repeat phone accessory buyer.',
            ],
            [
                'external_code' => 'IND-002',
                'name' => 'Bopha Kim',
                'email' => 'bopha.kim@example.com',
                'phone' => '+855 12 555 0102',
                'address' => 'Tuol Kork, Phnom Penh',
                'status' => 'active',
                'customer_type' => Customer::TYPE_INDIVIDUAL,
                'tier' => Customer::TIER_STANDARD,
                'currency' => 'USD',
                'language' => 'km',
                'timezone' => 'Asia/Phnom_Penh',
            ],
            [
                'external_code' => 'IND-003',
                'name' => 'Vibol Nguon',
                'email' => 'vibol.nguon@example.com',
                'phone' => '+855 12 555 0103',
                'status' => 'active',
                'customer_type' => Customer::TYPE_INDIVIDUAL,
                'tier' => Customer::TIER_PREMIUM,
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'notes' => 'Premium repeat customer - eligible for tier-2 pricing.',
            ],

            // ───── Business (B2B partners) ─────
            [
                'external_code' => 'BIZ-001',
                'name' => 'Angkor Logistics Co., Ltd.',
                'company_name' => 'Angkor Logistics Co., Ltd.',
                'email' => 'ap@angkorlogistics.kh',
                'phone' => '+855 23 555 1101',
                'address' => 'Sangkat Boeung Keng Kang 1, Phnom Penh',
                'status' => 'active',
                'customer_type' => Customer::TYPE_BUSINESS,
                'tier' => Customer::TIER_PREMIUM,
                'tax_id' => 'L100-100200001',
                'industry' => 'Logistics & Transportation',
                'website' => 'https://angkorlogistics.kh',
                'billing_city' => 'Phnom Penh',
                'billing_country' => 'KH',
                'billing_postal_code' => '120206',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'notes' => 'Quarterly fleet IT refresh - bulk laptop orders.',
            ],
            [
                'external_code' => 'BIZ-002',
                'name' => 'Mekong Microfinance PLC',
                'company_name' => 'Mekong Microfinance PLC',
                'email' => 'procurement@mekongmf.kh',
                'phone' => '+855 23 555 1102',
                'address' => 'Norodom Boulevard, Phnom Penh',
                'status' => 'active',
                'customer_type' => Customer::TYPE_BUSINESS,
                'tier' => Customer::TIER_ENTERPRISE,
                'tax_id' => 'L100-100200002',
                'industry' => 'Financial Services',
                'website' => 'https://mekongmf.kh',
                'billing_city' => 'Phnom Penh',
                'billing_country' => 'KH',
                'billing_postal_code' => '120201',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'notes' => 'Enterprise account, 200+ branch endpoint contracts.',
            ],
            [
                'external_code' => 'BIZ-003',
                'name' => 'Khmer Coffee Roasters',
                'company_name' => 'Khmer Coffee Roasters Co., Ltd.',
                'email' => 'office@khmercoffee.kh',
                'phone' => '+855 92 555 1103',
                'address' => 'Battambang Province',
                'status' => 'active',
                'customer_type' => Customer::TYPE_BUSINESS,
                'tier' => Customer::TIER_STANDARD,
                'tax_id' => 'L100-100200003',
                'industry' => 'Food & Beverage',
                'billing_city' => 'Battambang',
                'billing_country' => 'KH',
                'currency' => 'USD',
                'language' => 'km',
                'timezone' => 'Asia/Phnom_Penh',
            ],

            // ───── Tenant (provision-eligible SaaS) ─────
            [
                'external_code' => 'TEN-001',
                'name' => 'Sokimex Holdings',
                'company_name' => 'Sokimex Holdings',
                'email' => 'admin@sokimex.kh',
                'phone' => '+855 23 555 2201',
                'status' => 'active',
                'customer_type' => Customer::TYPE_TENANT,
                'tier' => Customer::TIER_ENTERPRISE,
                'tax_id' => 'L100-100300001',
                'industry' => 'Energy & Petroleum',
                'website' => 'https://sokimex.kh',
                'billing_city' => 'Phnom Penh',
                'billing_country' => 'KH',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'tenant_handle' => 'sokimex',
                'brand_primary_color' => '14 165 233',
                'notes' => 'Pending tenant provision on next subscription confirm.',
            ],
            [
                'external_code' => 'TEN-002',
                'name' => 'NagaCorp IT',
                'company_name' => 'NagaCorp Ltd.',
                'email' => 'it@nagacorp.kh',
                'phone' => '+855 23 555 2202',
                'status' => 'active',
                'customer_type' => Customer::TYPE_TENANT,
                'tier' => Customer::TIER_ENTERPRISE,
                'tax_id' => 'L100-100300002',
                'industry' => 'Hospitality',
                'billing_city' => 'Phnom Penh',
                'billing_country' => 'KH',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'tenant_handle' => 'nagacorp',
                'brand_primary_color' => '244 63 94',
                'notes' => 'High-volume tenant. Provisioning planned for Q2.',
            ],
            [
                'external_code' => 'TEN-003',
                'name' => 'BookMeBot Cambodia',
                'company_name' => 'BookMeBot Cambodia Co., Ltd.',
                'email' => 'founder@bookmebot.kh',
                'phone' => '+855 17 555 2203',
                'status' => 'active',
                'customer_type' => Customer::TYPE_TENANT,
                'tier' => Customer::TIER_STANDARD,
                'industry' => 'Software / SaaS',
                'website' => 'https://bookmebot.kh',
                'billing_city' => 'Phnom Penh',
                'billing_country' => 'KH',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'Asia/Phnom_Penh',
                'tenant_handle' => 'bookmebot',
                'brand_primary_color' => '139 92 246',
                'notes' => 'Startup tier - one-seat trial currently.',
            ],
        ];
    }
}
