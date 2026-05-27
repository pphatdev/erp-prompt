<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weighted Average Costing fields on Product.
 *
 *   total_quantity  — denormalized running on-hand (tenant-wide across all
 *                     warehouses). Faster than summing the movements ledger
 *                     for every WAC recompute.
 *   average_cost    — current weighted average unit cost. Recomputed on
 *                     stock-in inside StockService::recordMovement using:
 *                       new_avg = ((old_qty * old_avg) + (delta * unit_cost))
 *                                 / (old_qty + delta)
 *                     Out-movements drop total_quantity but do NOT touch
 *                     average_cost (WAC is an in-only cost basis).
 *   last_cost       — most recent stock-in unit_cost (convenience for "last
 *                     paid" reporting and PO suggestion seeds).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'total_quantity')) {
                $table->decimal('total_quantity', 14, 2)->default(0)->after('minimum_stock_level');
            }
            if (!Schema::hasColumn('products', 'average_cost')) {
                $table->decimal('average_cost', 14, 4)->default(0)->after('total_quantity');
            }
            if (!Schema::hasColumn('products', 'last_cost')) {
                $table->decimal('last_cost', 14, 4)->nullable()->after('average_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['total_quantity', 'average_cost', 'last_cost'] as $c) {
                if (Schema::hasColumn('products', $c)) $table->dropColumn($c);
            }
        });
    }
};
