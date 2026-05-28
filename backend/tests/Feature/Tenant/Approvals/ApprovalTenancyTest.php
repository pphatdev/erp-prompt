<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Approvals;

use App\Models\Central\Tenant;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Tenant\User;
use Illuminate\Support\Str;

class ApprovalTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'handle' => 'tenant-b', 'name' => 'Tenant B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_approval_requests(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $userA = User::first();
        
        $workflowA = ApprovalWorkflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Workflow',
            'module' => 'hrm',
            'is_active' => true,
        ]);
        
        $levelA = $workflowA->levels()->create([
            'sequence' => 1,
            'approver_id' => $userA->id,
        ]);

        $requestA = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflowA->id,
            'requester_id' => $userA->id,
            'current_level_id' => $levelA->id,
            'requestable_type' => 'App\\Models\\Tenant\\Leave',
            'requestable_id' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, ApprovalRequest::count(), 'Tenant B must not see Tenant A approval requests.');
        $this->assertNull(ApprovalRequest::find($requestA->id));
        $this->assertSame(0, ApprovalWorkflow::count(), 'Tenant B must not see Tenant A workflows.');
    }
}
