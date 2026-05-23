<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LedgerEntry;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Sales\Events\SubscriptionConfirmed;
use App\Tenants\Modules\Sales\Services\InvoiceService;
use App\Tenants\Modules\Sales\Services\OrderService;
use App\Tenants\Modules\Sales\Services\QuotationService;
use App\Tenants\Modules\Sales\Services\SubscriptionService;
use Illuminate\Support\Facades\Event;

/**
 * Hybrid Sales — happy-path end-to-end coverage.
 *
 * Walks the canonical mermaid flow from rules/hybrid_sales_business_flow.md:
 *   Customer → Quote → Confirm Quote → Order from Quote → Confirm Order →
 *     [Invoice + Stock deduction + Subscription] → Confirm Invoice (AR posted)
 *     → Confirm Subscription (event fired)
 */
class HybridSalesFlowTest extends TenantTestCase
{
    public function test_full_quote_to_fulfillment_happy_path(): void
    {
        Event::fake([SubscriptionConfirmed::class]);

        // 1. Seed Chart of Accounts (AR + Revenue) so InvoiceService can post.
        Account::create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'balance' => 0]);
        Account::create(['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'balance' => 0]);

        // 2. Seed Warehouse so the hardware deduction has a target.
        Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Main Warehouse']);

        // 3. Seed catalogue: one hardware product and one software product.
        $hardware = Product::create([
            'sku' => 'HW-001',
            'name' => 'Edge Server',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 1500.00,
            'minimum_stock_level' => 0,
        ]);
        $software = Product::create([
            'sku' => 'SW-001',
            'name' => 'ERP Cloud Suite',
            'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => 200.00,
            'minimum_stock_level' => 0,
        ]);

        // 4. Pre-stock the hardware (in-bound movement) so the fulfillment
        //    deduction has units to draw against.
        StockMovement::create([
            'product_id' => $hardware->id,
            'warehouse_id' => Warehouse::query()->first()->id,
            'type' => 'in',
            'quantity' => 10,
            'reference' => 'OPENING',
        ]);

        // 5. Create Customer + Quotation.
        $customer = Customer::create([
            'name' => 'Acme Corp', 'email' => 'buyer@acme.test', 'status' => 'active',
        ]);

        /** @var QuotationService $quotes */
        $quotes = app(QuotationService::class);
        $quote = $quotes->create([
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $hardware->id, 'quantity' => 2],
                ['product_id' => $software->id, 'quantity' => 5],
            ],
        ]);

        $this->assertSame(Quotation::STATUS_NEW, $quote->status);
        $this->assertEqualsWithDelta(2 * 1500 + 5 * 200, (float) $quote->total_amount, 0.01);

        // 6. Confirm Quote → create Order from Quote.
        $quotes->confirm($quote);
        $quote->refresh();
        $this->assertSame(Quotation::STATUS_CONFIRMED, $quote->status);

        /** @var OrderService $orders */
        $orders = app(OrderService::class);
        $order = $orders->createFromQuotation($quote);

        $this->assertSame(Order::STATUS_NEW, $order->status);
        $this->assertSame(2, $order->items->count());
        $this->assertSame($quote->id, $order->quotation_id);

        // 7. Confirm Order → fulfillment fans out.
        $orders->confirmOrder($order);
        $order->refresh()->load(['items', 'invoice', 'subscription']);

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);

        // 7a. Invoice exists, status still `new` (finance must confirm to post AR).
        $this->assertNotNull($order->invoice);
        $this->assertSame(Invoice::STATUS_NEW, $order->invoice->status);
        $this->assertEqualsWithDelta($order->total_amount, (float) $order->invoice->total_amount, 0.01);

        // 7b. Subscription exists for software lines only.
        $this->assertNotNull($order->subscription);
        $this->assertSame(Subscription::STATUS_NEW, $order->subscription->status);
        $this->assertSame(1, $order->subscription->items->count(), 'Only the software line should appear on the subscription.');
        $this->assertEqualsWithDelta(5 * 200, (float) $order->subscription->total_amount, 0.01);

        // 7c. Stock movement for hardware was recorded.
        $outMovement = StockMovement::where('product_id', $hardware->id)
            ->where('type', 'out')
            ->where('reference', "SO:{$order->order_number}")
            ->first();
        $this->assertNotNull($outMovement);
        $this->assertSame(-2, (int) $outMovement->quantity);

        // 8. Confirm Invoice → AR journal posts (balanced debit/credit).
        /** @var InvoiceService $invoices */
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->confirm($order->invoice);

        $this->assertSame(Invoice::STATUS_CONFIRMED, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);

        $journal = JournalEntry::find($invoice->journal_entry_id);
        $this->assertNotNull($journal);
        $debits = LedgerEntry::where('journal_entry_id', $journal->id)->sum('debit');
        $credits = LedgerEntry::where('journal_entry_id', $journal->id)->sum('credit');
        $this->assertEqualsWithDelta($debits, $credits, 0.01, 'AR journal must be balanced.');
        $this->assertEqualsWithDelta((float) $invoice->total_amount, (float) $debits, 0.01);

        // 9. Confirm Subscription → SubscriptionConfirmed event fires.
        /** @var SubscriptionService $subs */
        $subs = app(SubscriptionService::class);
        $sub = $subs->confirm($order->subscription);

        $this->assertSame(Subscription::STATUS_CONFIRMED, $sub->status);
        Event::assertDispatched(
            SubscriptionConfirmed::class,
            fn (SubscriptionConfirmed $e) => $e->subscription->id === $sub->id
        );
    }

    public function test_cannot_create_order_from_unconfirmed_quote(): void
    {
        $customer = Customer::create([
            'name' => 'Acme Corp', 'email' => 'buyer2@acme.test', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'HW-002', 'name' => 'Switch',
            'product_type' => Product::TYPE_HARDWARE, 'unit_price' => 100.00,
            'minimum_stock_level' => 0,
        ]);

        /** @var QuotationService $quotes */
        $quotes = app(QuotationService::class);
        $quote = $quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->expectException(\DomainException::class);
        app(OrderService::class)->createFromQuotation($quote);
    }

    public function test_cancelled_quote_cannot_be_confirmed(): void
    {
        $customer = Customer::create([
            'name' => 'Acme Corp', 'email' => 'buyer3@acme.test', 'status' => 'active',
        ]);
        $product = Product::create([
            'sku' => 'SW-002', 'name' => 'SaaS Add-on',
            'product_type' => Product::TYPE_SOFTWARE, 'unit_price' => 50.00,
            'minimum_stock_level' => 0,
        ]);

        /** @var QuotationService $quotes */
        $quotes = app(QuotationService::class);
        $quote = $quotes->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $quotes->cancel($quote, 'customer changed mind');

        $this->expectException(\DomainException::class);
        $quotes->confirm($quote->fresh());
    }
}
