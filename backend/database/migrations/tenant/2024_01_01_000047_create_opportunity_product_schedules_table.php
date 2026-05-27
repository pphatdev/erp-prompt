<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_product_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('opportunity_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('estimated_unit_price', 15, 2)->default(0);
            $table->string('cadence', 20)->default('one_time'); // one_time|monthly|annual
            $table->text('notes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_product_schedules');
    }
};
