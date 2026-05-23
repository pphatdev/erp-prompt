<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Phase 4: Invoices.
 *
 * One Invoice per Sales Order (1:1 enforced via unique order_id). When the
 * invoice transitions new → confirmed, InvoiceService posts a balanced
 * journal entry through AccountingService:
 *   DR Accounts Receivable     <total>
 *     CR Sales Revenue          <subtotal>
 *     CR Tax Payable            <tax_amount>   (if tax > 0)
 *
 * `journal_entry_id` is the audit link back to the GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('invoice_number')->unique();
                $table->uuid('order_id');
                $table->uuid('customer_id');
                $table->string('status', 20)->default('new');   // new|confirmed|cancelled|paid
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                // Audit trail back to the GL entry created on confirm.
                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->uuid('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique('order_id', 'invoices_order_id_unique');
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('restrict');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
                $table->index(['tenant_id', 'status'], 'invoices_status_idx');
                $table->index(['tenant_id', 'customer_id'], 'invoices_customer_idx');
            });
        }

        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('invoice_id');
                $table->uuid('order_item_id')->nullable();
                $table->uuid('product_id')->nullable();
                $table->uuid('variant_id')->nullable();
                $table->string('product_name');
                $table->string('product_type', 20)->nullable();
                $table->string('variant_sku')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
                $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('set null');
                $table->index(['tenant_id', 'invoice_id'], 'invoice_items_invoice_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
