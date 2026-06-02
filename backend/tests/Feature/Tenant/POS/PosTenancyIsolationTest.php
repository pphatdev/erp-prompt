<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\POS;

use App\Models\Central\Tenant;
use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Warehouse;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 - POS data created under tenant A must be structurally invisible from
 * tenant B's connection. Also asserts the cashier role + pos.* permissions
 * are seeded per tenant.
 */
class PosTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::create(['id' => 'pos-a', 'handle' => 'pos-a', 'name' => 'POS A']);
        $this->tenantB = Tenant::create(['id' => 'pos-b', 'handle' => 'pos-b', 'name' => 'POS B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_terminals_shifts_or_orders(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $warehouseA = Warehouse::create(['code' => 'WH-A', 'name' => 'WH A']);
        $cashierA = \App\Models\Tenant\User::create([
            'name' => 'Cashier A', 'email' => 'a@test.com', 'password' => 'secret123',
        ]);
        $terminalA = PosTerminal::create([
            'code' => 'REG-A', 'name' => 'Register A',
            'warehouse_id' => $warehouseA->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $shiftA = PosShift::create([
            'terminal_id' => $terminalA->id,
            'cashier_id' => $cashierA->id,
            'opened_at' => now(),
            'opening_float' => 100,
            'status' => PosShift::STATUS_OPEN,
        ]);
        $orderA = PosOrder::create([
            'order_number' => 'POS-AAA',
            'shift_id' => $shiftA->id,
            'terminal_id' => $terminalA->id,
            'cashier_id' => $cashierA->id,
            'subtotal' => 50,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 50,
            'status' => PosOrder::STATUS_PAID,
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, PosTerminal::count(), 'Tenant B must not see Tenant A terminals.');
        $this->assertSame(0, PosShift::count(), 'Tenant B must not see Tenant A shifts.');
        $this->assertSame(0, PosOrder::count(), 'Tenant B must not see Tenant A orders.');
        $this->assertNull(PosTerminal::find($terminalA->id));
        $this->assertNull(PosShift::find($shiftA->id));
        $this->assertNull(PosOrder::find($orderA->id));
    }

    public function test_cashier_role_and_pos_permissions_are_seeded_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $cashier = \App\Models\Tenant\Role::where('slug', 'cashier')->first();
        $this->assertNotNull($cashier, 'PosPermissionSeeder must create the cashier role.');

        $expectedSlugs = [
            'pos.shift.read', 'pos.shift.write',
            'pos.order.read', 'pos.order.write',
        ];
        foreach ($expectedSlugs as $slug) {
            $this->assertTrue(
                $cashier->permissions()->where('slug', $slug)->exists(),
                "cashier role must hold {$slug}."
            );
        }

        // Variance approve + void stay admin-only.
        $this->assertFalse(
            $cashier->permissions()->where('slug', 'pos.shift.approve')->exists(),
            'cashier must not hold pos.shift.approve.'
        );
        $this->assertFalse(
            $cashier->permissions()->where('slug', 'pos.order.void')->exists(),
            'cashier must not hold pos.order.void.'
        );
    }
}
