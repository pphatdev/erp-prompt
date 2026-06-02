<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point of Sale (POS) - Phase 1 schema.
 *
 * 5 tables:
 *   pos_terminals     - Register stations. One per physical checkout point.
 *                       FK warehouse_id pins stock-out source; petty_cash_account_id
 *                       names the GL account the drawer debits land in.
 *   pos_shifts        - Cashier sessions on a terminal. FSM:
 *                         open -> closed                       (variance == 0)
 *                         open -> closed -> variance_pending   (variance != 0)
 *                         variance_pending -> reconciled       (supervisor sign-off)
 *   pos_orders        - One checkout. Posted atomically by PosOrderService.
 *                       client_uuid carries a per-tenant unique constraint so an
 *                       offline-sync replay can never double-post.
 *   pos_order_items   - Line items. Snapshot product_name + variant_sku at sale
 *                       time so a later catalog rename doesn't rewrite history.
 *   pos_payments      - Tender records. payment_method = cash | card | wallet | manual.
 *                       Sum of payments.amount per order must equal grand_total
 *                       (enforced by PosOrderService::checkout, not DB).
 *
 * customer_id on pos_orders is nullable - walk-in sales are anonymous; cashiers
 * may attach an existing Sales\Customer for receipt-by-email / loyalty.
 *
 * variant_id on pos_order_items is nullable so terminals can sell base products
 * that have no variant set, while still supporting the existing variant catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_terminals')) {
            Schema::create('pos_terminals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 32);                 // e.g. "REG-01", surfaced on receipts
                $table->string('name');
                $table->uuid('warehouse_id');                // stock-out source
                $table->uuid('petty_cash_account_id')->nullable(); // GL account for drawer
                $table->string('location')->nullable();
                $table->string('status', 20)->default('active'); // active|disabled
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
                $table->foreign('petty_cash_account_id')->references('id')->on('accounts')->onDelete('restrict');

                $table->unique(['tenant_id', 'code'], 'pos_terminals_code_unique');
                $table->index(['tenant_id', 'status'], 'pos_terminals_status_idx');
            });
        }

        if (!Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('terminal_id');
                $table->uuid('cashier_id');                  // FK -> users.id (admin guard)
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->decimal('opening_float', 15, 2);     // counted cash at open
                $table->decimal('expected_cash', 15, 2)->nullable(); // float + sum(cash payments) - skims
                $table->decimal('closing_cash', 15, 2)->nullable();  // counted cash at close
                $table->decimal('variance', 15, 2)->nullable();      // closing - expected
                // FSM: open|closed|variance_pending|reconciled
                $table->string('status', 30)->default('open');
                $table->uuid('reconciled_by')->nullable();   // user who approved variance
                $table->timestamp('reconciled_at')->nullable();
                $table->uuid('variance_journal_entry_id')->nullable(); // posted on reconcile
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('restrict');
                $table->foreign('cashier_id')->references('id')->on('users')->onDelete('restrict');
                $table->foreign('variance_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

                $table->index(['tenant_id', 'terminal_id', 'status'], 'pos_shifts_term_status_idx');
                $table->index(['tenant_id', 'cashier_id', 'status'], 'pos_shifts_cashier_status_idx');
            });
        }

        if (!Schema::hasTable('pos_orders')) {
            Schema::create('pos_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_number');               // POS-####
                $table->uuid('shift_id');
                $table->uuid('terminal_id');                   // denormalized for indexed reads
                $table->uuid('cashier_id');                    // denormalized
                $table->string('client_uuid')->nullable();     // offline-sync idempotency key
                $table->uuid('customer_id')->nullable();       // optional Sales\Customer link

                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('tax_total', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('currency', 3)->default('USD');

                // FSM: paid|voided|refunded
                $table->string('status', 20)->default('paid');
                $table->uuid('journal_entry_id')->nullable(); // posted on checkout
                $table->uuid('void_journal_entry_id')->nullable(); // posted on void

                $table->timestamp('placed_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->uuid('voided_by')->nullable();
                $table->string('void_reason')->nullable();
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('shift_id')->references('id')->on('pos_shifts')->onDelete('restrict');
                $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('restrict');
                $table->foreign('cashier_id')->references('id')->on('users')->onDelete('restrict');
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
                $table->foreign('void_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

                $table->unique(['tenant_id', 'order_number'], 'pos_orders_number_unique');
                // Offline-sync replay guard. Partial index would be ideal but
                // sqlite/MySQL portability keeps it as a plain composite unique.
                $table->unique(['tenant_id', 'client_uuid'], 'pos_orders_client_uuid_unique');
                $table->index(['tenant_id', 'shift_id'], 'pos_orders_shift_idx');
                $table->index(['tenant_id', 'status'], 'pos_orders_status_idx');
                $table->index(['tenant_id', 'customer_id'], 'pos_orders_customer_idx');
            });
        }

        if (!Schema::hasTable('pos_order_items')) {
            Schema::create('pos_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->uuid('variant_id')->nullable();
                // Snapshots so a later product rename / delete doesn't mutate the receipt.
                $table->string('product_name');
                $table->string('product_sku');
                $table->string('variant_sku')->nullable();

                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('pos_orders')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

                $table->index(['tenant_id', 'order_id'], 'pos_order_items_order_idx');
            });
        }

        if (!Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                // cash | card | wallet | manual - PosPayment::PAYMENT_METHODS
                $table->string('payment_method', 20);
                $table->decimal('amount', 15, 2);
                $table->decimal('tendered', 15, 2)->nullable();  // cash given (cash method only)
                $table->decimal('change_due', 15, 2)->default(0);  // tendered - amount, cash only
                $table->string('reference_number')->nullable();    // card auth code, wallet ref
                $table->string('currency', 3)->default('USD');

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('pos_orders')->onDelete('cascade');
                $table->index(['tenant_id', 'order_id'], 'pos_payments_order_idx');
                $table->index(['tenant_id', 'payment_method'], 'pos_payments_method_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_terminals');
    }
};
