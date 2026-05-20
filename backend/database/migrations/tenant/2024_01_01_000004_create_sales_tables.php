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
        // Customers Table
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->text('address')->nullable();
                $table->string('status')->default('active'); // active, inactive
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Leads Table
        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->uuid('customer_id')->nullable();
                $table->decimal('estimated_value', 15, 2)->default(0);
                $table->string('status')->default('new'); // new, qualified, lost, won
                $table->string('source')->nullable(); // web, referral, etc.
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            });
        }

        // Orders Table
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_number')->unique();
                $table->uuid('customer_id');
                $table->decimal('total_amount', 15, 2);
                $table->string('status')->default('draft'); // draft, confirmed, shipped, delivered, cancelled
                $table->timestamp('ordered_at')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }

        // Order Items Table
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->string('product_name');
                $table->integer('quantity');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total', 15, 2);
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
    }
};
