<?php

namespace Tests\Feature;

use App\Models\Tenant\Warehouse;

class InventoryManagementTest extends TenantTestCase
{
    /**
     * Test Inventory Module endpoints listing accessibility.
     */
    public function test_inventory_module_endpoints()
    {
        $routes = ['/api/v1/products', '/api/v1/stock-movements'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test Inventory module features including Product creation and Stock Movements.
     */
    public function test_inventory_management_features_workflow()
    {
        // 1. Create a Product
        $prodPayload = [
            'sku' => 'PROD-SKU-99',
            'name' => 'Premium Office Desk',
            'description' => 'Solid wooden top with steel base.',
            'unit_price' => 299.99,
            'minimum_stock_level' => 5,
        ];

        $prodResponse = $this->tenantRequest('POST', '/api/v1/products', $prodPayload);
        $prodResponse->assertStatus(201);
        $productId = $prodResponse->json('data.id');
        $this->assertNotNull($productId);

        // 2. Create a Warehouse directly using Eloquent (since there is no REST API route for warehouses)
        $warehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name' => 'Main Distribution Center',
            'location' => 'Sector 7',
        ]);

        // 3. Record a Stock Movement (Inward)
        $movementPayload = [
            'product_id' => $productId,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 10,
            'reference' => 'PO-000123',
            'notes' => 'Initial stock arrival.',
        ];

        $movementResponse = $this->tenantRequest('POST', '/api/v1/stock-movements', $movementPayload);
        $movementResponse->assertStatus(201);
        
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);
    }
}
