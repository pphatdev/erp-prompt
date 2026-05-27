<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock reservations — soft holds on warehouse inventory consumed by online
 * cart / POS flows.
 *
 * Lifecycle:
 *   active → committed   (StockService posts the corresponding out-movement)
 *   active → cancelled   (operator / customer abandons the cart)
 *   active → expired     (TTL passes, daemon flips state and releases stock)
 *
 * `getNetAvailableStock(product, warehouse) = physical_qty - sum(active.qty)`
 * keeps two checkouts from over-selling the same units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('warehouse_id');
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 12, 2);

            // Free-form pointer back to whatever owns the cart / order
            // (e.g. "CART:abc123", "POS:terminal-3", "ORDER:SO-…").
            $table->string('reference')->nullable();

            $table->string('status', 20)->default('active'); // active|committed|cancelled|expired
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->uuid('actor_id')->nullable();

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->index('tenant_id');
            // Hot-path index — getNetAvailableStock filters by these three.
            $table->index(
                ['tenant_id', 'product_id', 'warehouse_id', 'status'],
                'stock_reservations_avail_idx'
            );
            // Daemon scan — expire pass scans active rows whose expires_at is past.
            $table->index(['status', 'expires_at'], 'stock_reservations_expiry_idx');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
