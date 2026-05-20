<?php

namespace Tests\Feature;

class SalesCRMTest extends TenantTestCase
{
    /**
     * Test Sales & CRM Module endpoints listing accessibility.
     */
    public function test_sales_module_endpoints()
    {
        $routes = ['/api/v1/customers', '/api/v1/leads', '/api/v1/orders'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test Sales & CRM module features including Leads and Order confirmation.
     */
    public function test_sales_crm_features_workflow()
    {
        // 1. Create a Customer
        $customerPayload = [
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'phone' => '1234567890',
            'company_name' => 'Acme Industries',
            'address' => '123 Main St',
        ];

        $custResponse = $this->tenantRequest('POST', '/api/v1/customers', $customerPayload);
        $custResponse->assertStatus(201);
        $customerId = $custResponse->json('data.id');
        $this->assertNotNull($customerId);

        // 2. Create a Lead linked to Customer
        $leadPayload = [
            'title' => 'Big Software Deal',
            'customer_id' => $customerId,
            'estimated_value' => 50000.00,
            'source' => 'web',
        ];

        $leadResponse = $this->tenantRequest('POST', '/api/v1/leads', $leadPayload);
        $leadResponse->assertStatus(201);
        $leadId = $leadResponse->json('data.id');

        // 3. Mark the Lead as won
        $winResponse = $this->tenantRequest('POST', "/api/v1/leads/{$leadId}/win");
        $winResponse->assertStatus(200)->assertJsonPath('data.status', 'won');

        // 4. Create an Order
        $orderPayload = [
            'order_number' => 'ORD-9999',
            'customer_id' => $customerId,
            'items' => [
                [
                    'product_name' => 'Premium Desk',
                    'quantity' => 2,
                    'unit_price' => 750.00,
                ]
            ],
            'total_amount' => 1500.00,
        ];

        $orderResponse = $this->tenantRequest('POST', '/api/v1/orders', $orderPayload);
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        // 5. Confirm the Order
        $confirmResponse = $this->tenantRequest('POST', "/api/v1/orders/{$orderId}/confirm");
        $confirmResponse->assertStatus(200)->assertJsonPath('data.status', 'confirmed');
    }
}
