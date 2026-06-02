<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Calendar;

use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEvent;
use App\Models\Tenant\Holiday;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 - calendar_events created under tenant A must be structurally invisible
 * from tenant B's connection. Also confirms the Calendar permission catalog
 * + role attachment runs cleanly on a fresh tenant DB.
 */
class CalendarTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::create(['id' => 'cal-a', 'handle' => 'cal-a', 'name' => 'Calendar A']);
        $this->tenantB = Tenant::create(['id' => 'cal-b', 'handle' => 'cal-b', 'name' => 'Calendar B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_custom_events_or_holidays(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $eventA = CalendarEvent::create([
            'title' => 'A-only town hall',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'category' => CalendarEvent::CATEGORY_COMPANY,
            'is_all_day' => false,
        ]);
        $holidayA = Holiday::create([
            'name' => 'A-only public day',
            'date' => '2026-04-15',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, CalendarEvent::count(), 'Tenant B must not see Tenant A calendar events.');
        $this->assertNull(CalendarEvent::find($eventA->id));
        $this->assertNull(Holiday::find($holidayA->id), 'Tenant B must not see Tenant A holidays.');
    }

    public function test_calendar_permission_catalogue_is_seeded_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $expected = [
            'calendar.event.read',
            'calendar.event.write',
            'calendar.event.delete',
            'calendar.event.override',
            'calendar.event.read.self',
            'calendar.event.write.self',
        ];

        foreach ($expected as $slug) {
            $this->assertNotNull(
                Permission::where('slug', $slug)->first(),
                "Permission '{$slug}' should be seeded."
            );
        }

        // Admin role gets the full set + the self variants.
        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        foreach ($expected as $slug) {
            $this->assertTrue(
                $admin->permissions()->where('slug', $slug)->exists(),
                "admin role must hold {$slug}."
            );
        }

        // Employee role (when present) gets only the .self variants.
        $employee = Role::where('slug', 'employee')->first();
        if ($employee) {
            $this->assertTrue(
                $employee->permissions()->where('slug', 'calendar.event.read.self')->exists(),
                'employee role must hold calendar.event.read.self.'
            );
            $this->assertTrue(
                $employee->permissions()->where('slug', 'calendar.event.write.self')->exists(),
                'employee role must hold calendar.event.write.self.'
            );
            $this->assertFalse(
                $employee->permissions()->where('slug', 'calendar.event.delete')->exists(),
                'employee role must NOT hold the admin-scope delete permission.'
            );
            $this->assertFalse(
                $employee->permissions()->where('slug', 'calendar.event.override')->exists(),
                'employee role must NOT hold the admin-scope override permission.'
            );
        }
    }
}
