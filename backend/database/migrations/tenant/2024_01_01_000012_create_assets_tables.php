<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Assets Table
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('asset_tag')->unique(); // Unique barcode/QR code reference
                $table->string('name');
                $table->string('category');
                $table->date('purchase_date');
                $table->decimal('purchase_cost', 15, 2);
                $table->decimal('current_value', 15, 2);
                $table->decimal('salvage_value', 15, 2)->default(0);
                $table->integer('useful_life_years');
                
                $table->string('depreciation_method')->default('straight_line'); // straight_line, declining_balance
                $table->string('status')->default('active'); // active, disposed, missing
                
                $table->uuid('custodian_id')->nullable(); // Employee responsible
                $table->uuid('location_id')->nullable(); // Branch/Dept link (generic string for now)
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
                $table->foreign('custodian_id')->references('id')->on('employees')->onDelete('set null');
            });
        }

        // Depreciation Logs Table
        if (!Schema::hasTable('depreciation_logs')) {
            Schema::create('depreciation_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('asset_id');
                $table->date('period_date'); // E.g., End of Month
                $table->decimal('depreciation_amount', 15, 2);
                $table->decimal('accumulated_depreciation', 15, 2);
                $table->decimal('book_value', 15, 2);
                
                // FMS Integration link
                $table->uuid('journal_entry_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_logs');
        Schema::dropIfExists('assets');
    }
};
