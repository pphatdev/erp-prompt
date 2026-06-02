<?php

namespace Database\Seeders;

use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's central database.
     */
    public function run(): void
    {
        // Ensure the demo tenant exists
        $tenant = Tenant::firstOrCreate(
            ['handle' => 'demo'],
            ['id' => 'demo', 'name' => 'Demo Enterprise']
        );

        // After creating the tenant, stancl/tenancy usually fires events to create the DB and run migrations,
        // but if not, we can trigger it manually, or the user can run `php artisan tenants:migrate` and `php artisan tenants:seed`.

        $this->command?->info('Central database seeded! Demo tenant is ready.');
        $this->command?->info('');
        $this->command?->info('To populate three additional demo tenants (acme + mekong + sokimex) with realistic data, run:');
        $this->command?->info('  php artisan db:seed --class=Database\\Seeders\\DemoTenantsSeeder');
    }
}
