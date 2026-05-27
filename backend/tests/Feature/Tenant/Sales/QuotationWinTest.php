<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Sales;

use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Quotation;
use App\Tenants\Modules\Sales\Services\QuotationService;
use Tests\Feature\TenantTestCase;

class QuotationWinTest extends TenantTestCase
{
    private QuotationService $quotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotes = app(QuotationService::class);
    }

    public function test_winning_quotation_with_existing_customer_creates_draft_sale_order(): void
    {
        $customer = Customer::create([
            'name' => 'Existing Co', 'customer_type' => 'business', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-A', 'name' => 'Cloud', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 100.00, 'minimum_stock_level' => 0,
        ]);
        $quote = $this->quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderCountBefore = Order::count();

        $won = $this->quotes->win($quote);

        $this->assertSame(Quotation::STATUS_WON, $won->status);
        $this->assertSame($orderCountBefore + 1, Order::count());
        $this->assertNotNull($won->order);
        $this->assertSame(Order::STATUS_DRAFT, $won->order->status);
        $this->assertSame($won->id, $won->order->quotation_id);
    }

    public function test_winning_software_quotation_with_lead_only_creates_tenant_customer(): void
    {
        $product = Product::create([
            'sku' => 'SW-B', 'name' => 'ERP', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 250.00, 'minimum_stock_level' => 0,
        ]);

        $lead = Lead::create([
            'first_name' => 'Anna', 'last_name' => 'Park',
            'email' => 'anna@northwind.test', 'phone' => '+855 12 000 111',
            'customer_type' => 'business', 'address' => '12 Riverside Rd',
            'status' => 'new',
        ]);
        $opp = Opportunity::create([
            'title' => 'Northwind Deal',
            'lead_id' => $lead->id,
            'stage' => Opportunity::STAGE_QUALIFIED,
        ]);

        $quote = $this->quotes->create([
            'from_opportunity_id' => $opp->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $customerCountBefore = Customer::count();
        $contactCountBefore  = CrmContact::count();

        $won = $this->quotes->win($quote);

        $this->assertSame(Quotation::STATUS_WON, $won->status);
        $this->assertSame($customerCountBefore + 1, Customer::count());
        $this->assertSame($contactCountBefore + 1, CrmContact::count());
        $this->assertNotNull($won->customer_id);
        $this->assertNotNull($won->order);

        $customer = Customer::find($won->customer_id);
        // Software line ⇒ Customer materialised as a tenant with the slugified
        // fullName as its handle (overrides the lead's declared customer_type).
        $this->assertSame(Customer::TYPE_TENANT, $customer->customer_type);
        $this->assertSame('anna-park', $customer->tenant_handle);
        $this->assertSame('Anna Park', $customer->name);
        $this->assertSame('anna@northwind.test', $customer->email);
        $this->assertSame('+855 12 000 111', $customer->phone);

        // Primary contact carries the lead's person data, not a placeholder.
        $contact = CrmContact::where('customer_id', $customer->id)->where('is_primary', true)->first();
        $this->assertNotNull($contact);
        $this->assertSame('Anna', $contact->first_name);
        $this->assertSame('Park', $contact->last_name);
        $this->assertSame('anna@northwind.test', $contact->email);

        // Lead is linked to the new Customer + marked qualified.
        $lead->refresh();
        $this->assertSame($won->customer_id, $lead->customer_id);
        $this->assertSame('qualified', $lead->status);
    }

    public function test_winning_hardware_only_quotation_creates_business_customer(): void
    {
        $hw = Product::create([
            'sku' => 'HW-K1', 'name' => 'Edge Switch', 'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 500.00, 'minimum_stock_level' => 0,
        ]);
        $lead = Lead::create([
            'first_name' => 'Bob', 'last_name' => 'Lin',
            'email' => 'bob@hw.test', 'phone' => '+1 555 000',
            'customer_type' => 'business', 'address' => '99 Industry Way',
            'status' => 'new',
        ]);
        $opp = Opportunity::create([
            'title' => 'Hardware deal',
            'lead_id' => $lead->id,
            'stage' => Opportunity::STAGE_QUALIFIED,
        ]);
        $quote = $this->quotes->create([
            'from_opportunity_id' => $opp->id,
            'items' => [['product_id' => $hw->id, 'quantity' => 1]],
        ]);

        $won = $this->quotes->win($quote);
        $customer = Customer::find($won->customer_id);

        // No software lines ⇒ keep the lead's declared customer_type, no handle.
        $this->assertSame(Customer::TYPE_BUSINESS, $customer->customer_type);
        $this->assertNull($customer->tenant_handle);
    }

    public function test_tenant_handle_collision_appends_suffix(): void
    {
        // Pre-occupy the obvious handle so the next derivation must collide-recover.
        Customer::create([
            'name' => 'Existing Anna Park',
            'customer_type' => Customer::TYPE_TENANT,
            'tenant_handle' => 'anna-park',
            'status' => 'active',
        ]);

        $product = Product::create([
            'sku' => 'SW-K', 'name' => 'Plan K', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 10.00, 'minimum_stock_level' => 0,
        ]);
        $lead = Lead::create([
            'first_name' => 'Anna', 'last_name' => 'Park',
            'email' => 'anna2@elsewhere.test', 'phone' => '+1 555 222',
            'customer_type' => 'business', 'address' => '1 Other St',
            'status' => 'new',
        ]);
        $opp = Opportunity::create([
            'title' => 'Second Anna deal',
            'lead_id' => $lead->id,
            'stage' => Opportunity::STAGE_QUALIFIED,
        ]);
        $quote = $this->quotes->create([
            'from_opportunity_id' => $opp->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $won = $this->quotes->win($quote);
        $customer = Customer::find($won->customer_id);

        $this->assertSame(Customer::TYPE_TENANT, $customer->customer_type);
        // Base slug is taken — must have been mutated with a "-XXXX" suffix.
        $this->assertNotSame('anna-park', $customer->tenant_handle);
        $this->assertStringStartsWith('anna-park-', $customer->tenant_handle);
    }

    public function test_win_is_idempotent_when_already_won(): void
    {
        $customer = Customer::create([
            'name' => 'Already Won Co', 'customer_type' => 'business', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-C', 'name' => 'Plan C', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 99.00, 'minimum_stock_level' => 0,
        ]);
        $quote = $this->quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->quotes->win($quote);
        $orderCountAfterFirst = Order::count();

        $this->quotes->win($quote->fresh()); // second call

        $this->assertSame($orderCountAfterFirst, Order::count(), 'Re-winning must not create a second Order.');
    }

    public function test_cannot_win_a_lost_quotation(): void
    {
        $customer = Customer::create([
            'name' => 'Lost Co', 'customer_type' => 'business', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-D', 'name' => 'Plan D', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 10.00, 'minimum_stock_level' => 0,
        ]);
        $quote = $this->quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->quotes->lose($quote, 'Budget cut');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot win a lost quotation');
        $this->quotes->win($quote->fresh());
    }

    public function test_lose_closes_originating_lead_as_unqualified(): void
    {
        $product = Product::create([
            'sku' => 'SW-E', 'name' => 'Plan E', 'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 10.00, 'minimum_stock_level' => 0,
        ]);
        $lead = Lead::create(['title' => 'Lost Lead', 'status' => 'qualified']);
        $opp  = Opportunity::create([
            'title' => 'Lost Deal',
            'lead_id' => $lead->id,
            'stage' => Opportunity::STAGE_QUALIFIED,
        ]);
        $quote = $this->quotes->create([
            'from_opportunity_id' => $opp->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->quotes->lose($quote, 'Lost to competitor');

        $lead->refresh();
        $this->assertSame('unqualified', $lead->status);
    }
}
