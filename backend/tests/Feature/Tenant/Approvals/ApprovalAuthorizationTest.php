<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Approvals;

use App\Models\Central\Tenant;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\User;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class ApprovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'Tenant A']);
        tenancy()->initialize($this->tenant);
        $this->seed(TenantDatabaseSeeder::class);
        
        $this->adminUser = User::where('email', 'admin@example.com')->first();
        $this->regularUser = User::factory()->create([
            'email' => 'regular@example.com',
            'is_active' => true,
        ]);
        
        // Ensure regular user has the action execution permission for testing the domain authorization
        $this->regularUser->roles()->attach(
            \App\Models\Tenant\Role::where('slug', 'admin')->first()
        );
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_only_designated_approver_can_process_action(): void
    {
        $workflow = ApprovalWorkflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Workflow',
            'module' => 'hrm',
            'is_active' => true,
        ]);
        
        $level = $workflow->levels()->create([
            'sequence' => 1,
            'approver_id' => $this->adminUser->id, // Admin is the approver
        ]);

        $request = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'requester_id' => $this->regularUser->id,
            'current_level_id' => $level->id,
            'requestable_type' => 'App\\Models\\Tenant\\Leave',
            'requestable_id' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        // Regular user attempts to approve but is NOT the designated approver
        $this->actingAs($this->regularUser, 'api');

        $response = $this->postJson("/api/v1/approval-requests/{$request->id}/process", [
            'action' => 'approved',
        ], [
            'X-Tenant-Handle' => $this->tenant->handle,
        ]);

        $response->assertStatus(500); // Because ApprovalService throws Exception("User is not authorized for this approval level.")
        
        // Admin user (designated approver) attempts to approve
        $this->actingAs($this->adminUser, 'api');
        
        $response2 = $this->postJson("/api/v1/approval-requests/{$request->id}/process", [
            'action' => 'approved',
        ], [
            'X-Tenant-Handle' => $this->tenant->handle,
        ]);

        $response2->assertStatus(200);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);
    }
}
