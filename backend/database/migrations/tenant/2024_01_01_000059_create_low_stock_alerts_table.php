<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent log of every threshold-crossing. The notification queue is
 * fire-and-forget but procurement managers need a durable list of "what
 * went red, when, while you were asleep" to drive POs. The unique
 * (product_id, status=open) constraint elsewhere is enforced in code so
 * we don't open a second alert for a SKU that's already red.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id');
            $table->integer('threshold');
            $table->decimal('quantity_at_alert', 14, 2);
            $table->string('status', 16)->default('open'); // open | acknowledged | resolved
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_alerts');
    }
};
