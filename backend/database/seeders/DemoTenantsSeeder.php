<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Central seeder: bootstraps three demo tenants end-to-end.
 *
 * For each handle:
 *   1. Upserts the central `tenants` row (idempotent on handle PK).
 *   2. Ensures a default `domains` row exists matching `<handle>.<system_domain>`.
 *   3. Provisions the physical tenant database via Stancl (idempotent).
 *   4. Runs the tenant migrations (`tenants:migrate --tenants=<handle>`).
 *   5. Calls TenantDatabaseSeeder + DemoTenantSeeder inside the tenant
 *      context so the new DB lands with roles, permissions, modules, a
 *      demo catalogue, stock anchor, vendors, warehouses, and customers.
 *
 * Run on the central DB:
 *   php artisan db:seed --class=Database\\Seeders\\DemoTenantsSeeder
 *
 * Result: three tenants ready to log into immediately via X-Tenant-Handle.
 * Re-running is safe - everything is upsert-or-skip.
 */
class DemoTenantsSeeder extends Seeder
{
    /**
     * @var array<int, array{handle: string, name: string, brand: string}>
     */
    private array $tenants = [
        ['handle' => 'acme',     'name' => 'Acme Industries',  'brand' => '59 130 246'],   // primary blue
        ['handle' => 'mekong',   'name' => 'Mekong Mart',      'brand' => '16 185 129'],   // emerald
        ['handle' => 'sokimex',  'name' => 'Sokimex Holdings', 'brand' => '244 63 94'],    // rose
    ];

    public function run(): void
    {
        // Guard against accidentally running on a tenant connection.
        if (tenant()?->getTenantKey()) {
            throw new \RuntimeException(
                'DemoTenantsSeeder is a CENTRAL seeder. Do not invoke it via tenants:run. ' .
                'Use `php artisan db:seed --class=Database\\Seeders\\DemoTenantsSeeder` from the central context.'
            );
        }

        $systemDomain = config('platform.system_domain', 'localhost');

        foreach ($this->tenants as $row) {
            $handle = $row['handle'];
            $this->command?->info("--- Demo tenant: {$handle} ({$row['name']}) ---");

            $tenant = $this->upsertTenant($handle, $row['name']);
            $this->ensureDomain($tenant, "{$handle}.{$systemDomain}");

            try {
                $this->migrateTenant($handle);
                $this->seedTenant($tenant);
            } catch (Throwable $e) {
                $this->command?->error("Tenant {$handle} bootstrap failed: " . $e->getMessage());
                continue;
            }
        }

        $this->command?->info('');
        $this->command?->info('Demo tenants ready. Log in with X-Tenant-Handle: acme | mekong | sokimex');
    }

    private function upsertTenant(string $handle, string $name): Tenant
    {
        return Tenant::firstOrCreate(
            ['handle' => $handle],
            ['id' => $handle, 'name' => $name]
        );
    }

    private function ensureDomain(Tenant $tenant, string $domain): void
    {
        if (!Schema::connection(config('tenancy.database.central_connection', 'pgsql'))->hasTable('domains')) {
            return;
        }
        DB::table('domains')->updateOrInsert(
            ['domain' => $domain],
            ['tenant_id' => $tenant->getTenantKey(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Run tenant migrations for one handle. Idempotent - Laravel skips
     * migrations already recorded in the tenant DB's `migrations` table.
     */
    private function migrateTenant(string $handle): void
    {
        $this->command?->line("  -> Running tenant migrations for {$handle}...");
        $exitCode = Artisan::call('tenants:migrate', [
            '--tenants' => [$handle],
            '--force' => true,
        ]);
        if ($exitCode !== 0) {
            throw new \RuntimeException("tenants:migrate exited with code {$exitCode} for {$handle}.");
        }
    }

    /**
     * Initialise the tenant connection, then call TenantDatabaseSeeder +
     * DemoTenantSeeder inside that context. tenancy()->end() always runs
     * so a failure here doesn't leak the connection into the next iteration.
     */
    private function seedTenant(Tenant $tenant): void
    {
        $this->command?->line("  -> Seeding base + demo pack for {$tenant->getTenantKey()}...");
        tenancy()->initialize($tenant);
        try {
            $this->call(TenantDatabaseSeeder::class);
            $this->call(DemoTenantSeeder::class);
        } finally {
            tenancy()->end();
        }
    }
}
