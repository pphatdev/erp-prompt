<?php

namespace Tests\Feature;

class ReportingAnalyticsTest extends TenantTestCase
{
    /**
     * Test Reporting Module endpoints listing accessibility.
     */
    public function test_reporting_module_endpoints()
    {
        $this->tenantRequest('GET', '/api/v1/dashboards')->assertStatus(200);
    }

    /**
     * Test Reporting module features including Dashboards and customizable Metric Widgets.
     */
    public function test_reporting_dashboard_and_widgets_workflow()
    {
        // 1. Create a Dashboard
        $dashPayload = [
            'name' => 'Corporate Sales Overview',
            'is_default' => true,
        ];

        $dashResponse = $this->tenantRequest('POST', '/api/v1/dashboards', $dashPayload);
        $dashResponse->assertStatus(201);
        $dashboardId = $dashResponse->json('data.id');
        $this->assertNotNull($dashboardId);

        // 2. Create a Widget linked to the Dashboard
        $widgetPayload = [
            'dashboard_id' => $dashboardId,
            'type' => 'bar_chart',
            'data_source' => 'sales_revenue',
            'config' => [
                'x_axis' => 'month',
                'y_axis' => 'total_revenue',
                'color' => '#4F46E5',
            ]
        ];

        $widgetResponse = $this->tenantRequest('POST', '/api/v1/widgets', $widgetPayload);
        $widgetResponse->assertStatus(201);
        
        $this->assertDatabaseHas('widgets', [
            'dashboard_id' => $dashboardId,
            'type' => 'bar_chart',
            'data_source' => 'sales_revenue',
        ]);
    }
}
