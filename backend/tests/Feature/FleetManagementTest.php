<?php

namespace Tests\Feature;

class FleetManagementTest extends TenantTestCase
{
    /**
     * Test Fleet Module endpoints listing accessibility.
     */
    public function test_fleet_module_endpoints()
    {
        $routes = ['/api/v1/vehicles', '/api/v1/maintenance-logs', '/api/v1/fuel-logs'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test Fleet Management module features including Vehicles, Fuel Logs, and Maintenance Logs.
     */
    public function test_fleet_management_features_workflow()
    {
        // 1. Create a Vehicle
        $vehiclePayload = [
            'registration_number' => 'B-7777-XYZ',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'vin' => '1YV1HP88B97XXXXXX',
            'current_mileage' => 15000,
        ];

        $vehicleResponse = $this->tenantRequest('POST', '/api/v1/vehicles', $vehiclePayload);
        $vehicleResponse->assertStatus(201);
        $vehicleId = $vehicleResponse->json('data.id');
        $this->assertNotNull($vehicleId);

        // 2. Log Fuel Transaction
        $fuelPayload = [
            'vehicle_id' => $vehicleId,
            'fill_date' => '2026-05-19',
            'liters' => 45.5,
            'cost' => 65.25,
            'mileage_at_fill' => 15200,
        ];

        $fuelResponse = $this->tenantRequest('POST', '/api/v1/fuel-logs', $fuelPayload);
        $fuelResponse->assertStatus(201);

        // 3. Log Maintenance Service
        $maintenancePayload = [
            'vehicle_id' => $vehicleId,
            'service_type' => 'oil_change',
            'service_date' => '2026-05-19',
            'mileage_at_service' => 15250,
            'cost' => 85.00,
            'notes' => 'Regular engine oil and filter change.',
        ];

        $maintenanceResponse = $this->tenantRequest('POST', '/api/v1/maintenance-logs', $maintenancePayload);
        $maintenanceResponse->assertStatus(201);
        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicleId,
            'service_type' => 'oil_change',
        ]);
    }
}
