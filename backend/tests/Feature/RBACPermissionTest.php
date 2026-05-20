<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TenantDatabaseSeeder;

class RBACPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $admin;
    protected $viewer;


    protected function setUp(): void
    {
        parent::setUp();

        // Initialize tenant context
        $this->tenant = Tenant::create(['id' => 'test-tenant', 'handle' => 'test', 'name' => 'Test Company']);
        tenancy()->initialize($this->tenant);

        // Seed permissions
        $this->seed(TenantDatabaseSeeder::class);

        // Create Admin User
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->roles()->attach(Role::where('slug', 'admin')->first());

        // Create Viewer User (No roles)
        $this->viewer = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * Test that hasPermission helper works correctly.
     */
    public function test_user_has_permission_check()
    {
        $this->assertTrue($this->admin->hasPermission('iam.users.read'));
        $this->assertFalse($this->viewer->hasPermission('iam.users.read'));
    }

    /**
     * Test that Admin can list users but Viewer cannot.
     */
    public function test_permission_enforcement_on_routes()
    {
        // As Admin
        $response = $this->actingAs($this->admin, 'api')
                         ->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->getJson('/api/v1/users');
        $response->assertStatus(200);

        // As Viewer
        $response = $this->actingAs($this->viewer, 'api')
                         ->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->getJson('/api/v1/users');
        $response->assertStatus(403);
    }

    /**
     * Test token refresh endpoint behavior.
     */
    /**
     * Test user login endpoint.
     */
    public function test_user_login_endpoint()
    {
        // 1. Invalid credentials
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/login', [
                             'email' => 'admin@test.com',
                             'password' => 'wrong-password',
                         ]);
        $response->assertStatus(401)
                 ->assertJson([
                     'message' => 'Invalid credentials.'
                 ]);

        // 2. Successful login
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/login', [
                             'email' => 'admin@test.com',
                             'password' => 'password',
                         ]);
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => [
                         'id',
                         'name',
                         'email',
                         'roles',
                     ],
                     'access_token',
                     'token_type',
                 ]);

        // 3. Inactive user login
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@test.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/login', [
                             'email' => 'inactive@test.com',
                             'password' => 'password',
                         ]);
        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Account is inactive.'
                 ]);
    }

    /**
     * Test authenticated user profile endpoint.
     */
    public function test_authenticated_user_profile_me()
    {
        // Without authentication
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->getJson('/api/v1/auth/me');
        $response->assertStatus(401);

        // With authentication
        $response = $this->actingAs($this->admin, 'api')
                         ->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->getJson('/api/v1/auth/me');
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id',
                         'name',
                         'email',
                         'roles',
                     ]
                 ]);
    }

    /**
     * Test user logout endpoint.
     */
    public function test_user_logout_endpoint()
    {
        // Without authentication
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/logout');
        $response->assertStatus(401);

        // With authentication
        $response = $this->actingAs($this->admin, 'api')
                         ->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/logout');
        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Successfully logged out.'
                 ]);
    }

    /**
     * Test IAM Module endpoints listing accessibility.
     */
    public function test_iam_module_endpoints()
    {
        $routes = ['/api/v1/users', '/api/v1/roles'];
        foreach ($routes as $route) {
            $this->actingAs($this->admin, 'api')
                 ->withHeaders(['X-Tenant-Handle' => 'test'])
                 ->getJson($route)
                 ->assertStatus(200);
        }
    }

    public function test_token_refresh_endpoint()
    {
        // Without authentication
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/refresh');
        $response->assertStatus(401);

        // With authentication
        $response = $this->actingAs($this->admin, 'api')
                         ->withHeaders(['X-Tenant-Handle' => 'test'])
                         ->postJson('/api/v1/auth/refresh');
                          
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'token_type'
                 ]);
    }
}
