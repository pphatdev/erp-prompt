<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procure-to-Pay (P2P) — Purchase Orders + Items.
 *
 * Status flow:
 *   draft → submitted → approved → receiving → received       (happy path)
 *   draft → cancelled
 *   submitted → cancelled
 *   approved → cancelled (only if no receipt yet)
 *
 * `receiving` is the partial-receipt state — at least one line received but
 * not all. Service flips to `received` once every line's received_qty matches
 * the ordered_qty.
 *
 * Receiving posts a stock_movement (type=in) per delta — actual stock on
 * hand is always reconstructed from the movements ledger, not stored on the
 * PO row. The PO just tracks `received_qty` for line-level reconciliation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('po_number')->unique();
                $table->uuid('supplier_id');
                $table->uuid('warehouse_id'); // default destination — per-line override is a future enhancement
                $table->string('status', 20)->default('draft'); // draft|submitted|approved|receiving|received|cancelled
                $table->date('order_date')->nullable();
                $table->date('expected_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();

                $table->uuid('ordered_by')->nullable();
                $table->uuid('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
                $table->index('tenant_id');
                $table->index(['tenant_id', 'status'], 'purchase_orders_status_idx');
                $table->index('supplier_id');
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('purchase_order_id');
                $table->uuid('product_id');
                $table->uuid('variant_id')->nullable();
                // Snapshots so historical PO totals don't shift when catalogue
                // prices are edited later.
                $table->string('product_name');
                $table->string('variant_sku')->nullable();
                $table->decimal('ordered_qty', 12, 2);
                $table->decimal('received_qty', 12, 2)->default(0);
                $table->decimal('unit_cost', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
                $table->index(['tenant_id', 'purchase_order_id'], 'po_items_po_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
