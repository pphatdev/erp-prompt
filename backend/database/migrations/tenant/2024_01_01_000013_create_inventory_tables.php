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
        // Warehouses Table
        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('location')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Suppliers Table
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Products Table
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('sku')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('unit_price', 15, 2);
                $table->integer('minimum_stock_level')->default(0); // For low stock alerts
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Stock Movements Table (The Ledger)
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->uuid('warehouse_id');
                $table->string('type'); // in, out, transfer
                $table->integer('quantity'); // Positive for 'in', negative for 'out'
                $table->string('reference')->nullable(); // PO Number, Sales Order Number, etc.
                $table->text('notes')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('warehouses');
    }
};
