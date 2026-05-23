<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Phase 1: Product foundations.
 *
 * 1. Adds `product_type` to `products` (`hardware` | `software`). This is the
 *    branching key for fulfillment downstream: hardware items deduct from
 *    stock, software items provision a subscription.
 * 2. Adds `is_active` so retired products don't pollute the catalogue but
 *    historical Quotation/Order/Invoice rows still resolve.
 * 3. Creates `product_variants` — each variant has its own SKU and price
 *    plus a free-form `attributes` jsonb (color/size/plan-tier/term/etc).
 *    StockMovement and OrderItem reference variant_id when set; null variant
 *    means "the product itself" for catalogue entries with no axes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'product_type')) {
                    // hardware | software — drives the fulfillment split in
                    // OrderService::confirm.
                    $table->string('product_type', 20)->default('hardware')->after('name');
                }
                if (!Schema::hasColumn('products', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('minimum_stock_level');
                }
                if (!Schema::hasColumn('products', 'description_long')) {
                    // Pulled into Quotation/Invoice line snapshots so retired
                    // descriptions don't change historical documents.
                    $table->text('description_long')->nullable()->after('description');
                }
            });
        }

        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->string('sku')->unique();
                $table->string('name');
                $table->decimal('unit_price', 14, 2);
                // Free-form axes: {color, size, plan_tier, term, seat_count, ...}
                // Variants stay flexible across hardware/software without
                // schema churn when a new axis appears.
                $table->jsonb('attributes')->nullable();
                $table->boolean('is_active')->default(true);

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->index(['tenant_id', 'product_id'], 'product_variants_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                foreach (['product_type', 'is_active', 'description_long'] as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
