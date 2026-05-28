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

class ApprovalStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'Tenant A']);
        tenancy()->initialize($this->tenant);
        $this->seed(TenantDatabaseSeeder::class);
        
        $this->adminUser = User::where('email', 'admin@example.com')->first();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_cannot_approve_already_rejected_request(): void
    {
        $workflow = ApprovalWorkflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Workflow',
            'module' => 'hrm',
            'is_active' => true,
        ]);
        
        $level = $workflow->levels()->create([
            'sequence' => 1,
            'approver_id' => $this->adminUser->id,
        ]);

        $request = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'requester_id' => $this->adminUser->id,
            'current_level_id' => $level->id,
            'requestable_type' => 'App\\Models\\Tenant\\Leave',
            'requestable_id' => (string) Str::uuid(),
            'status' => 'rejected', // Already rejected
        ]);

        $this->actingAs($this->adminUser, 'api');
        
        $response = $this->postJson("/api/v1/approval-requests/{$request->id}/process", [
            'action' => 'approved',
        ], [
            'X-Tenant-Handle' => $this->tenant->handle,
        ]);

        // Expect Exception("Cannot process action on a non-pending request.")
        $response->assertStatus(500); 
    }

    public function test_rejection_requires_comment(): void
    {
        $workflow = ApprovalWorkflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Workflow',
            'module' => 'hrm',
            'is_active' => true,
        ]);
        
        $level = $workflow->levels()->create([
            'sequence' => 1,
            'approver_id' => $this->adminUser->id,
        ]);

        $request = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'requester_id' => $this->adminUser->id,
            'current_level_id' => $level->id,
            'requestable_type' => 'App\\Models\\Tenant\\Leave',
            'requestable_id' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->adminUser, 'api');
        
        // No comment provided
        $response = $this->postJson("/api/v1/approval-requests/{$request->id}/process", [
            'action' => 'rejected',
        ], [
            'X-Tenant-Handle' => $this->tenant->handle,
        ]);

        // Validation error 422
        $response->assertStatus(422); 
        $response->assertJsonValidationErrors('comment');
    }
}
