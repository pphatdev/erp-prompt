<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Models\Tenant\Role;
use Database\Seeders\TenantDatabaseSeeder;

abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $admin;
    protected $viewer;

    /**
     * Define the parameters to pass to the migrate:fresh command.
     *
     * Overrides both base TestCase and RefreshDatabase trait.
     *
     * @return array
     */
    protected function migrateFreshUsing()
    {
        return [
            '--path' => [
                'database/migrations/central',
                'database/migrations',
                'database/migrations/tenant',
            ],
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
        ];
    }

    /**
     * Automatically handle setup for all tenant-scoped tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test-tenant', 'handle' => 'test', 'name' => 'Test Company']);
        tenancy()->initialize($this->tenant);

        $this->seed(TenantDatabaseSeeder::class);

        $adminRole = Role::where('slug', 'admin')->first();
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->roles()->attach($adminRole);
    }

    /**
     * Helper to create a secondary Viewer user.
     */
    protected function setUpViewerUser(): User
    {
        $viewerRole = Role::where('slug', 'viewer')->first();
        $this->viewer = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->viewer->roles()->attach($viewerRole);
        
        return $this->viewer;
    }

    /**
     * Send an authenticated JSON request with tenant handle header as the Admin user.
     */
    protected function tenantRequest(string $method, string $uri, array $data = [], array $headers = [])
    {
        return $this->actingAs($this->admin, 'api')
                    ->withHeaders(array_merge(['X-Tenant-Handle' => 'test'], $headers))
                    ->json($method, $uri, $data);
    }
}
