<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Ecommerce;

use App\Models\Central\Tenant;
use App\Models\Tenant\EcomCart;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomRefund;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 — Ecom data created under tenant A must be structurally invisible
 * from tenant B's connection. BelongsToTenant trait + global scope is the
 * only safety net here; this test guards against regression.
 */
class EcomTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::create(['id' => 'ecom-a', 'handle' => 'ecom-a', 'name' => 'Ecom A']);
        $this->tenantB = Tenant::create(['id' => 'ecom-b', 'handle' => 'ecom-b', 'name' => 'Ecom B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_shoppers_carts_orders_refunds(): void
    {
        // Seed tenant A with one of each entity.
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $shopperA = EcomCustomer::create([
            'email' => 'a@shopper.test',
            'password' => 'secret123',
            'first_name' => 'Alice',
        ]);
        $cartA = EcomCart::create([
            'customer_id' => $shopperA->id,
            'status' => EcomCart::STATUS_ACTIVE,
            'subtotal' => 0,
            'currency' => 'USD',
        ]);
        $orderA = EcomOrder::create([
            'order_number' => 'ECOO-AAA',
            'customer_id' => $shopperA->id,
            'status' => EcomOrder::STATUS_PENDING_PAYMENT,
            'subtotal' => 100,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'currency' => 'USD',
        ]);
        $refundA = EcomRefund::create([
            'refund_number' => 'ECOR-AAA',
            'order_id' => $orderA->id,
            'status' => EcomRefund::STATUS_REQUESTED,
            'is_partial' => false,
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // Swap to tenant B and assert blindness.
        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, EcomCustomer::count(), 'Tenant B must not see Tenant A shoppers.');
        $this->assertSame(0, EcomCart::count(), 'Tenant B must not see Tenant A carts.');
        $this->assertSame(0, EcomOrder::count(), 'Tenant B must not see Tenant A orders.');
        $this->assertSame(0, EcomRefund::count(), 'Tenant B must not see Tenant A refunds.');
        $this->assertNull(EcomCustomer::find($shopperA->id));
        $this->assertNull(EcomOrder::find($orderA->id));
        $this->assertNull(EcomRefund::find($refundA->id));
    }

    public function test_shopper_role_and_storefront_permission_are_seeded_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $shopper = \App\Models\Tenant\Role::where('slug', 'shopper')->first();
        $this->assertNotNull($shopper, 'EcommercePermissionSeeder must create the shopper role.');

        $perm = \App\Models\Tenant\Permission::where('slug', 'ecommerce.storefront.read')->first();
        $this->assertNotNull($perm);
        $this->assertTrue(
            $shopper->permissions()->where('slug', 'ecommerce.storefront.read')->exists(),
            'shopper role must hold ecommerce.storefront.read.'
        );
    }
}
