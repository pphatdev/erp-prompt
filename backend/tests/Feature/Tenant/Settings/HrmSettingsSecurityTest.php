<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Settings;

use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Tests\Feature\TenantTestCase;

/**
 * Phase 9 Item 2 - Security gate audit for /api/v1/settings.
 *
 * Asserts:
 *   - GET /api/v1/settings requires `settings.read`.
 *   - PUT /api/v1/settings requires `settings.write`.
 *   - The standard `employee` role (self-service only) cannot read or
 *     mutate settings - they get 403 on both endpoints.
 *   - The `admin` role (full grant) passes both endpoints.
 */
class HrmSettingsSecurityTest extends TenantTestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRole = Role::where('slug', 'employee')->first();
        $this->employee = User::create([
            'name' => 'Self-Service Employee',
            'email' => 'selfservice@test.com',
            'password' => 'password',
        ]);
        $this->employee->roles()->attach($employeeRole);
    }

    public function test_employee_cannot_read_settings(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/settings');

        $response->assertStatus(403);
    }

    public function test_employee_cannot_read_settings_by_group(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/settings?group=hrm');

        $response->assertStatus(403);
    }

    public function test_employee_cannot_write_settings(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->putJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'hrm.payroll.default_payday', 'value' => 15],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_read_settings(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/settings?group=hrm');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['key', 'value', 'group']]]);
    }

    public function test_admin_can_write_settings(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->putJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'hrm.payroll.default_payday', 'value' => 15],
                ],
            ]);

        $response->assertStatus(200);
    }

    public function test_public_settings_endpoint_remains_open_for_login_branding(): void
    {
        // Public endpoint serves logo/primary color before the user
        // authenticates - it MUST stay reachable without settings.read.
        $response = $this->withHeaders(['X-Tenant-Handle' => 'test'])
            ->getJson('/api/v1/settings/public');

        $response->assertStatus(200);
    }
}
