<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Phase 3: Sales Order extension.
 *
 * Wires `orders` to a parent `quotations` row (one-to-one is enforced at the
 * service layer — DB allows one quote → one order so the foreign key sits on
 * orders.quotation_id, unique).
 *
 * Order items get the same snapshot fields as quotation_items so downstream
 * fulfillment (inventory deduction for hardware, subscription for software)
 * doesn't have to round-trip through products.
 *
 * Existing rows: `order_items.quantity` is widened to numeric to match
 * quotation_items (half-units already supported there).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            // Normalize legacy status default ('draft') to the canonical
            // 'new' so the hybrid-sales status machine doesn't reject existing
            // rows on the next status read.
            \DB::table('orders')->where('status', 'draft')->update(['status' => 'new']);

            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'quotation_id')) {
                    $table->uuid('quotation_id')->nullable()->after('order_number');
                }
                if (!Schema::hasColumn('orders', 'due_date')) {
                    $table->date('due_date')->nullable()->after('ordered_at');
                }
                if (!Schema::hasColumn('orders', 'subtotal')) {
                    $table->decimal('subtotal', 14, 2)->default(0)->after('total_amount');
                }
                if (!Schema::hasColumn('orders', 'tax_amount')) {
                    $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
                }
                if (!Schema::hasColumn('orders', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('due_date');
                }
                if (!Schema::hasColumn('orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
                }
                if (!Schema::hasColumn('orders', 'cancel_reason')) {
                    $table->string('cancel_reason')->nullable()->after('cancelled_at');
                }
            });

            // FK + unique added separately so the column-presence checks above
            // are idempotent (Postgres rejects ALTER if the FK already exists).
            $hasFk = collect(\DB::select(
                "SELECT 1 FROM information_schema.table_constraints
                 WHERE table_name = 'orders' AND constraint_name = 'orders_quotation_id_foreign'"
            ))->isNotEmpty();
            if (!$hasFk) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->foreign('quotation_id')
                        ->references('id')->on('quotations')->onDelete('set null');
                    $table->unique('quotation_id', 'orders_quotation_id_unique');
                });
            }
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'product_id')) {
                    $table->uuid('product_id')->nullable()->after('order_id');
                }
                if (!Schema::hasColumn('order_items', 'variant_id')) {
                    $table->uuid('variant_id')->nullable()->after('product_id');
                }
                if (!Schema::hasColumn('order_items', 'product_type')) {
                    $table->string('product_type', 20)->nullable()->after('product_name');
                }
                if (!Schema::hasColumn('order_items', 'variant_sku')) {
                    $table->string('variant_sku')->nullable()->after('product_type');
                }
                if (!Schema::hasColumn('order_items', 'due_date')) {
                    $table->date('due_date')->nullable()->after('total');
                }
                if (!Schema::hasColumn('order_items', 'notes')) {
                    $table->text('notes')->nullable()->after('due_date');
                }
            });

            // Widen quantity from integer to decimal(12,2) for fractional
            // service quantities (e.g. monthly seats prorated). Postgres
            // raw cast — Blueprint can't do ALTER COLUMN TYPE cleanly.
            \DB::statement('ALTER TABLE order_items ALTER COLUMN quantity TYPE NUMERIC(12,2) USING quantity::numeric(12,2)');

            $hasProductFk = collect(\DB::select(
                "SELECT 1 FROM information_schema.table_constraints
                 WHERE table_name = 'order_items' AND constraint_name = 'order_items_product_id_foreign'"
            ))->isNotEmpty();
            if (!$hasProductFk) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                    $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('restrict');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['product_id', 'variant_id', 'product_type', 'variant_sku', 'due_date', 'notes'] as $col) {
                    if (Schema::hasColumn('order_items', $col)) {
                        try { $table->dropForeign([$col]); } catch (\Throwable $e) { /* no fk */ }
                        $table->dropColumn($col);
                    }
                }
            });
            \DB::statement('ALTER TABLE order_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)::integer');
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['quotation_id', 'due_date', 'subtotal', 'tax_amount', 'confirmed_at', 'cancelled_at', 'cancel_reason'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        try { $table->dropForeign([$col]); } catch (\Throwable $e) { /* no fk */ }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
