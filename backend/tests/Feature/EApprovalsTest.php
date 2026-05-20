<?php

namespace Tests\Feature;

class EApprovalsTest extends TenantTestCase
{
    /**
     * Test eApprovals Module endpoints listing accessibility.
     */
    public function test_approvals_module_endpoints()
    {
        $this->tenantRequest('GET', '/api/v1/approval-workflows')->assertStatus(200);
    }

    /**
     * Test creating a multi-level approval workflow in eApprovals.
     */
    public function test_approvals_workflow_creation()
    {
        $this->setUpViewerUser();

        $payload = [
            'name' => 'Leave Request Workflow',
            'module' => 'hcm',
            'type' => 'sequential',
            'levels' => [
                [
                    'sequence' => 1,
                    'approver_role' => 'admin',
                ],
                [
                    'sequence' => 2,
                    'approver_id' => $this->viewer->id,
                ]
            ]
        ];

        $response = $this->tenantRequest('POST', '/api/v1/approval-workflows', $payload);
        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Leave Request Workflow')
                 ->assertJsonPath('module', 'hcm')
                 ->assertJsonCount(2, 'levels');
                 
        $this->assertDatabaseHas('approval_workflows', [
            'name' => 'Leave Request Workflow',
            'module' => 'hcm',
        ]);
    }
}
