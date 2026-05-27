<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative ledger for inter-warehouse transfers. The stock_movements
 * table records the two halves (transfer_out / transfer_in) but they're
 * loose pairs — this table gives the transfer an identity so an operator
 * can ask "what happened on transfer T-123?", report on in-transit units,
 * and back-out a transfer if needed.
 *
 * Status flow:
 *   draft       → editable
 *   in_transit  → out posted (deducts source), waiting for receipt
 *   received    → in posted (credits destination), terminal
 *   cancelled   → terminal; reversal movements posted if in_transit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('transfer_number')->unique();
            $table->uuid('from_warehouse_id');
            $table->uuid('to_warehouse_id');
            $table->string('status', 16)->default('draft');

            // Lifecycle stamps
            $table->uuid('initiated_by')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->uuid('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_warehouse_id')->references('id')->on('warehouses');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('stock_transfer_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 14, 2);
            $table->decimal('received_qty', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_id')->references('id')->on('stock_transfers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->index('stock_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
