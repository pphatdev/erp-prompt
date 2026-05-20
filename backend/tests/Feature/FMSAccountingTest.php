<?php

namespace Tests\Feature;

class FMSAccountingTest extends TenantTestCase
{
    /**
     * Test FMS Module endpoints listing accessibility.
     */
    public function test_fms_module_endpoints()
    {
        $routes = ['/api/v1/accounts', '/api/v1/ledger'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test FMS Accounting features including creating accounts and posting double-entry ledger journals.
     */
    public function test_fms_accounting_features_workflow()
    {
        // 1. Create Debit Account
        $debitAccResponse = $this->tenantRequest('POST', '/api/v1/accounts', [
            'code' => '1010',
            'name' => 'Cash at Bank',
            'type' => 'asset',
        ]);
        $debitAccResponse->assertStatus(201);
        $debitAccId = $debitAccResponse->json('data.id');

        // 2. Create Credit Account
        $creditAccResponse = $this->tenantRequest('POST', '/api/v1/accounts', [
            'code' => '3010',
            'name' => 'Owner Equity',
            'type' => 'equity',
        ]);
        $creditAccResponse->assertStatus(201);
        $creditAccId = $creditAccResponse->json('data.id');

        // 3. Post a Double-Entry Journal Entry
        $ledgerPayload = [
            'reference_number' => 'JV-2026-001',
            'description' => 'Initial capital injection',
            'entry_date' => '2026-05-19',
            'lines' => [
                [
                    'account_id' => $debitAccId,
                    'debit' => 10000.00,
                    'credit' => 0,
                ],
                [
                    'account_id' => $creditAccId,
                    'debit' => 0,
                    'credit' => 10000.00,
                ]
            ]
        ];

        $ledgerResponse = $this->tenantRequest('POST', '/api/v1/ledger', $ledgerPayload);
        $ledgerResponse->assertStatus(201)->assertJsonPath('data.reference_number', 'JV-2026-001');

        $this->assertDatabaseHas('journal_entries', [
            'reference_number' => 'JV-2026-001',
        ]);
    }
}
