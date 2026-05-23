<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Phase 2: Quotations.
 *
 * `quotations` is the top of the sales funnel after a customer record exists.
 * Status flow (enforced by QuotationService):
 *   new --(confirm)--> confirmed --(convert)--> Sales Order
 *   new --(cancel)---> cancelled (terminal — equivalent to "close lead")
 *
 * Line items snapshot the product name and unit price at quote time so a
 * later catalogue price change doesn't retroactively alter the quote total.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('quote_number')->unique();
                $table->uuid('customer_id');
                $table->string('status', 20)->default('new');   // new|confirmed|cancelled
                $table->date('quote_date');
                $table->date('valid_until')->nullable();
                $table->date('due_date')->nullable();           // requested delivery
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->uuid('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->uuid('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
                $table->index(['tenant_id', 'status'], 'quotations_status_idx');
                $table->index(['tenant_id', 'customer_id'], 'quotations_customer_idx');
            });
        }

        if (!Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('quotation_id');
                $table->uuid('product_id');
                $table->uuid('variant_id')->nullable();
                // Snapshot fields — kept even if product/variant later changes.
                $table->string('product_name');
                $table->string('product_type', 20);   // hardware|software, captured for downstream split
                $table->string('variant_sku')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('restrict');
                $table->index(['tenant_id', 'quotation_id'], 'quotation_items_quote_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
