<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Sales;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\SubscriptionItem;
use App\Tenants\Modules\Sales\Services\SubscriptionService;
use Tests\Feature\TenantTestCase;

class SubscriptionLifecycleTest extends TenantTestCase
{
    private SubscriptionService $subs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subs = app(SubscriptionService::class);
    }

    public function test_renew_extends_end_date_and_issues_invoice(): void
    {
        [$sub, $product] = $this->seedActiveSubscription();
        $originalEnd = $sub->end_date->copy();
        $invoiceCountBefore = Invoice::count();

        $renewed = $this->subs->renew($sub);

        $this->assertSame(Subscription::STATUS_ACTIVE, $renewed->status);
        $this->assertTrue($renewed->end_date->greaterThan($originalEnd), 'end_date must advance.');
        $this->assertSame($invoiceCountBefore + 1, Invoice::count(), 'A renewal Invoice must be issued.');
    }

    public function test_upgrade_swaps_variant_and_bills_delta(): void
    {
        [$sub, $oldProduct] = $this->seedActiveSubscription();
        $newProduct = Product::create([
            'sku' => 'SW-PRO', 'name' => 'Pro Plan', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 300.00, 'minimum_stock_level' => 0,
        ]);
        $invoiceCountBefore = Invoice::count();

        $this->subs->changePlan($sub, [
            'product_id'        => $newProduct->id,
            'target_product_id' => $oldProduct->id,
        ], 'upgrade');

        $sub->refresh()->load('items');
        $line = $sub->items->first();
        $this->assertSame($newProduct->id, $line->product_id);
        $this->assertSame($invoiceCountBefore + 1, Invoice::count(), 'Upgrade must bill a delta Invoice.');

        $deltaInvoice = Invoice::orderByDesc('created_at')->first();
        $this->assertGreaterThan(0, (float) $deltaInvoice->total_amount);
    }

    public function test_downgrade_swaps_variant_and_emits_credit(): void
    {
        [$sub, $oldProduct] = $this->seedActiveSubscription(unitPrice: 200.0);
        $cheaperProduct = Product::create([
            'sku' => 'SW-LITE', 'name' => 'Lite Plan', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 50.00, 'minimum_stock_level' => 0,
        ]);
        $invoiceCountBefore = Invoice::count();

        $this->subs->changePlan($sub, [
            'product_id'        => $cheaperProduct->id,
            'target_product_id' => $oldProduct->id,
        ], 'downgrade');

        $this->assertSame($invoiceCountBefore + 1, Invoice::count());
        $creditInvoice = Invoice::orderByDesc('created_at')->first();
        $this->assertLessThan(0, (float) $creditInvoice->total_amount, 'Downgrade credit must be negative.');
    }

    public function test_cancel_marks_subscription_cancelled(): void
    {
        [$sub, ] = $this->seedActiveSubscription();
        $cancelled = $this->subs->cancel($sub, 'Customer request');

        $this->assertSame(Subscription::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cannot_renew_a_cancelled_subscription(): void
    {
        [$sub, ] = $this->seedActiveSubscription();
        $this->subs->cancel($sub, 'Done');

        $this->expectException(\DomainException::class);
        $this->subs->renew($sub->fresh());
    }

    public function test_expire_due_subscriptions_flips_past_end_date_to_expired(): void
    {
        [$sub, ] = $this->seedActiveSubscription();
        $sub->update(['end_date' => now()->subDay()->toDateString()]);

        $affected = $this->subs->expireDueSubscriptions();

        $this->assertSame(1, $affected);
        $this->assertSame(Subscription::STATUS_EXPIRED, $sub->fresh()->status);
    }

    public function test_expire_leaves_future_dated_subscriptions_active(): void
    {
        [$sub, ] = $this->seedActiveSubscription();
        $sub->update(['end_date' => now()->addMonth()->toDateString()]);

        $affected = $this->subs->expireDueSubscriptions();

        $this->assertSame(0, $affected);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->fresh()->status);
    }

    /**
     * @return array{0: Subscription, 1: Product}
     */
    private function seedActiveSubscription(float $unitPrice = 100.0): array
    {
        $customer = Customer::create([
            'name' => 'Sub Co', 'customer_type' => 'business', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-BASE-' . uniqid(), 'name' => 'Base Plan',
            'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => $unitPrice, 'minimum_stock_level' => 0,
        ]);

        $order = Order::create([
            'order_number' => 'SO-TEST-' . uniqid(),
            'customer_id'  => $customer->id,
            'status'       => Order::STATUS_CONFIRM,
            'subtotal'     => $unitPrice,
            'tax_amount'   => 0,
            'total_amount' => $unitPrice,
            'confirmed_at' => now(),
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => Product::TYPE_SOFTWARE,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total' => $unitPrice,
        ]);

        $sub = Subscription::create([
            'subscription_number' => 'SUB-TEST-' . uniqid(),
            'order_id'            => $order->id,
            'customer_id'         => $customer->id,
            'status'              => Subscription::STATUS_ACTIVE,
            'start_date'          => now()->subDays(5)->toDateString(),
            'end_date'            => now()->addMonth()->toDateString(),
            'billing_cycle'       => Subscription::CYCLE_MONTHLY,
            'total_amount'        => $unitPrice,
        ]);
        SubscriptionItem::create([
            'subscription_id' => $sub->id,
            'order_item_id'   => $orderItem->id,
            'product_id'      => $product->id,
            'product_name'    => $product->name,
            'quantity'        => 1,
            'unit_price'      => $unitPrice,
            'line_total'      => $unitPrice,
        ]);

        return [$sub->fresh('items'), $product];
    }
}
