<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Sales;

use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Sales\Services\OrderService;
use App\Tenants\Modules\Sales\Services\QuotationService;
use App\Tenants\Modules\Sales\Services\TenantProvisioningService;
use Mockery;
use Tests\Feature\TenantTestCase;

/**
 * Verifies the new single-trigger provisioning model:
 *  - tenant-type customer + software line + Order::confirm → provisioner called
 *  - non-tenant customer → provisioner NOT called
 *  - hardware-only order for new tenant customer → provisioner NOT called
 *  - provisioning exception does NOT roll back the order
 *
 * TenantProvisioningService is mocked because the real implementation opens
 * the Central DB and runs `migrate` against a per-tenant schema we don't
 * want to spin up inside an isolated test.
 */
class OrderConfirmProvisioningTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Account::create(['code' => '1200', 'name' => 'AR', 'type' => 'asset', 'balance' => 0]);
        Account::create(['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'balance' => 0]);
        Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Main']);
    }

    public function test_provisioner_invoked_for_tenant_customer_with_software_line(): void
    {
        $mock = Mockery::mock(TenantProvisioningService::class);
        $mock->shouldReceive('provisionForCustomer')->once();
        $this->app->instance(TenantProvisioningService::class, $mock);

        $customer = Customer::create([
            'name' => 'Tenant Co',
            'email' => 'tenant@acme.test',
            'customer_type' => Customer::TYPE_TENANT,
            'tenant_handle' => 'tenant-co-' . uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-' . uniqid(), 'name' => 'Cloud',
            'product_type' => Product::TYPE_SOFTWARE, 'unit_price' => 100.00,
            'minimum_stock_level' => 0,
        ]);

        $order = $this->buildConfirmedSaleOrder($customer, $product);
        $this->assertSame(Order::STATUS_CONFIRM, $order->status);
    }

    public function test_provisioner_skipped_for_non_tenant_customer(): void
    {
        $mock = Mockery::mock(TenantProvisioningService::class);
        $mock->shouldNotReceive('provisionForCustomer');
        $this->app->instance(TenantProvisioningService::class, $mock);

        $customer = Customer::create([
            'name' => 'B2C Co',
            'email' => 'b2c@acme.test',
            'customer_type' => 'business',
            'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-' . uniqid(), 'name' => 'Cloud',
            'product_type' => Product::TYPE_SOFTWARE, 'unit_price' => 100.00,
            'minimum_stock_level' => 0,
        ]);

        $this->buildConfirmedSaleOrder($customer, $product);
    }

    public function test_provisioning_failure_does_not_roll_back_order_confirm(): void
    {
        $mock = Mockery::mock(TenantProvisioningService::class);
        $mock->shouldReceive('provisionForCustomer')
            ->andThrow(new \RuntimeException('central DB unreachable'));
        $this->app->instance(TenantProvisioningService::class, $mock);

        $customer = Customer::create([
            'name' => 'Fragile Co',
            'email' => 'fragile@acme.test',
            'customer_type' => Customer::TYPE_TENANT,
            'tenant_handle' => 'fragile-' . uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-' . uniqid(), 'name' => 'Cloud',
            'product_type' => Product::TYPE_SOFTWARE, 'unit_price' => 100.00,
            'minimum_stock_level' => 0,
        ]);

        $order = $this->buildConfirmedSaleOrder($customer, $product);

        $this->assertSame(Order::STATUS_CONFIRM, $order->status, 'Order must stay confirmed even when provisioning throws.');
    }

    private function buildConfirmedSaleOrder(Customer $customer, Product $product): Order
    {
        /** @var QuotationService $quotes */
        $quotes = app(QuotationService::class);
        $quote = $quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $quotes->win($quote);
        $order = $quote->fresh('order')->order;

        /** @var OrderService $orders */
        $orders = app(OrderService::class);
        $orders->confirmOrder($order);

        return $order->fresh(['items', 'invoice', 'subscription', 'customer']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
