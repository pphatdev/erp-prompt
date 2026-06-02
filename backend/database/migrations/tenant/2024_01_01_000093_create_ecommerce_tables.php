<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ecommerce (B2C storefront) — Phase 1 schema.
 *
 * 9 tables:
 *   ecom_customers      — shopper accounts (separate from Sales\Customer, which is B2B).
 *   ecom_addresses      — per-shopper address book; default shipping/billing flags.
 *   ecom_carts          — bound to ecom_customers.id OR session_token (guest).
 *   ecom_cart_items     — references products + variants; snapshots unit_price.
 *   ecom_orders         — ECOO numbering, FSM: pending_payment → paid → fulfilling
 *                         → shipped → delivered → cancelled / refunded.
 *                         Optional sales_order_id + invoice_id link to Sales pipeline.
 *   ecom_order_items    — line snapshots (product_name, variant_sku, unit_price).
 *   ecom_payments       — provider + provider_charge_id + raw_payload jsonb;
 *                         client_uuid unique to make checkout idempotent.
 *   ecom_refunds        — ECOR numbering, FSM: requested → approved → processing
 *                         → completed / rejected. partial flag, credit_note_id link.
 *   ecom_refund_items   — line-level partial refund tracking.
 *
 * Money columns mirror Sales (decimal(14,2)). Quantities mirror Inventory
 * (decimal(12,2)) so an ecom order line can match a product variant.
 *
 * No `slug` on products today, so the storefront resolves products via SKU
 * (see ecom_cart_items.product_id FK; storefront layer maps slug→product).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecom_customers')) {
            Schema::create('ecom_customers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->string('password');                  // hashed via model cast
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();         // encrypted via model cast
                $table->boolean('is_guest')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('remember_token', 100)->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'email'], 'ecom_customers_email_unique');
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('ecom_addresses')) {
            Schema::create('ecom_addresses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id');
                $table->string('label')->nullable();         // "Home", "Office"
                $table->string('recipient_name');
                $table->string('phone')->nullable();
                $table->string('line1');
                $table->string('line2')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country', 2);                // ISO-3166-1 alpha-2
                $table->boolean('is_default_shipping')->default(false);
                $table->boolean('is_default_billing')->default(false);

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('customer_id')->references('id')->on('ecom_customers')->onDelete('cascade');
                $table->index(['tenant_id', 'customer_id'], 'ecom_addresses_customer_idx');
            });
        }

        if (!Schema::hasTable('ecom_carts')) {
            Schema::create('ecom_carts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->nullable();     // null for guest carts
                $table->string('session_token')->nullable(); // guest cart key
                $table->string('status', 20)->default('active'); // active|merged|expired|converted
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->timestamp('expires_at')->nullable(); // mirrors longest reservation
                $table->timestamp('converted_at')->nullable();
                $table->uuid('converted_order_id')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('customer_id')->references('id')->on('ecom_customers')->nullOnDelete();
                $table->index(['tenant_id', 'customer_id'], 'ecom_carts_customer_idx');
                $table->index(['tenant_id', 'session_token'], 'ecom_carts_session_idx');
                $table->index(['tenant_id', 'status'], 'ecom_carts_status_idx');
            });
        }

        if (!Schema::hasTable('ecom_cart_items')) {
            Schema::create('ecom_cart_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cart_id');
                $table->uuid('product_id');
                $table->uuid('variant_id')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);        // snapshot at add-to-cart
                $table->decimal('line_total', 14, 2);
                $table->uuid('reservation_id')->nullable();  // FK to stock_reservations

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('cart_id')->references('id')->on('ecom_carts')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
                $table->foreign('reservation_id')->references('id')->on('stock_reservations')->nullOnDelete();
                $table->index(['tenant_id', 'cart_id'], 'ecom_cart_items_cart_idx');
            });
        }

        if (!Schema::hasTable('ecom_orders')) {
            Schema::create('ecom_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_number');               // ECOO-####
                $table->uuid('customer_id')->nullable();      // null for one-shot guest checkouts
                $table->uuid('cart_id')->nullable();
                // Downstream Sales pipeline link — set on confirm.
                $table->uuid('sales_order_id')->nullable();
                $table->uuid('invoice_id')->nullable();

                $table->string('status', 30)->default('pending_payment');
                // Statuses: pending_payment|paid|fulfilling|shipped|delivered|cancelled|refunded

                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('shipping_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('USD');

                // Snapshot addresses at order time (decoupled from address book edits).
                $table->jsonb('shipping_address')->nullable();
                $table->jsonb('billing_address')->nullable();

                // Fulfillment / tracking
                $table->string('carrier')->nullable();
                $table->string('tracking_number')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();

                $table->timestamp('placed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('customer_id')->references('id')->on('ecom_customers')->nullOnDelete();
                $table->foreign('cart_id')->references('id')->on('ecom_carts')->nullOnDelete();
                $table->foreign('sales_order_id')->references('id')->on('orders')->nullOnDelete();
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();

                $table->unique(['tenant_id', 'order_number'], 'ecom_orders_number_unique');
                $table->index(['tenant_id', 'status'], 'ecom_orders_status_idx');
                $table->index(['tenant_id', 'customer_id'], 'ecom_orders_customer_idx');
            });
        }

        if (!Schema::hasTable('ecom_order_items')) {
            Schema::create('ecom_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->uuid('variant_id')->nullable();
                // Snapshots — survive product rename / delete.
                $table->string('product_name');
                $table->string('product_sku');
                $table->string('variant_sku')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('ecom_orders')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('restrict');
                $table->index(['tenant_id', 'order_id'], 'ecom_order_items_order_idx');
            });
        }

        if (!Schema::hasTable('ecom_payments')) {
            Schema::create('ecom_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->string('provider', 30);              // stripe|aba|wing|manual
                $table->string('provider_charge_id')->nullable();
                $table->string('status', 20);                // pending|succeeded|failed|refunded|partial_refund
                $table->decimal('amount', 14, 2);
                $table->decimal('gateway_fee', 14, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('client_uuid');               // idempotency key from checkout
                $table->jsonb('raw_payload')->nullable();    // provider response body
                $table->string('failure_code')->nullable();
                $table->string('failure_message')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('failed_at')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('order_id')->references('id')->on('ecom_orders')->onDelete('cascade');
                $table->unique(['tenant_id', 'client_uuid'], 'ecom_payments_client_uuid_unique');
                $table->index(['tenant_id', 'provider_charge_id'], 'ecom_payments_charge_idx');
                $table->index(['tenant_id', 'status'], 'ecom_payments_status_idx');
            });
        }

        if (!Schema::hasTable('ecom_refunds')) {
            Schema::create('ecom_refunds', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('refund_number');             // ECOR-####
                $table->uuid('order_id');
                $table->uuid('payment_id')->nullable();      // gateway charge being refunded
                $table->uuid('credit_note_id')->nullable();  // FK to credit_notes when fms credit_notes_enabled

                $table->string('status', 20)->default('requested');
                // Statuses: requested|approved|processing|completed|rejected

                $table->boolean('is_partial')->default(false);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('USD');
                $table->text('reason')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('provider_refund_id')->nullable();

                $table->uuid('requested_by')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('order_id')->references('id')->on('ecom_orders')->onDelete('cascade');
                $table->foreign('payment_id')->references('id')->on('ecom_payments')->nullOnDelete();
                // credit_note_id FK left unenforced until FMS Credit Notes ships
                // (skills/fms credit_notes table exists per migration 000087 — wire below).
                $table->foreign('credit_note_id')->references('id')->on('credit_notes')->nullOnDelete();

                $table->unique(['tenant_id', 'refund_number'], 'ecom_refunds_number_unique');
                $table->index(['tenant_id', 'status'], 'ecom_refunds_status_idx');
                $table->index(['tenant_id', 'order_id'], 'ecom_refunds_order_idx');
            });
        }

        if (!Schema::hasTable('ecom_refund_items')) {
            Schema::create('ecom_refund_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('refund_id');
                $table->uuid('order_item_id');
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->boolean('restock')->default(true);   // false for damaged-on-return

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('refund_id')->references('id')->on('ecom_refunds')->onDelete('cascade');
                $table->foreign('order_item_id')->references('id')->on('ecom_order_items')->onDelete('restrict');
                $table->index(['tenant_id', 'refund_id'], 'ecom_refund_items_refund_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_refund_items');
        Schema::dropIfExists('ecom_refunds');
        Schema::dropIfExists('ecom_payments');
        Schema::dropIfExists('ecom_order_items');
        Schema::dropIfExists('ecom_orders');
        Schema::dropIfExists('ecom_cart_items');
        Schema::dropIfExists('ecom_carts');
        Schema::dropIfExists('ecom_addresses');
        Schema::dropIfExists('ecom_customers');
    }
};
