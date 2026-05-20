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
        // Vehicles Table
        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('registration_number')->unique();
                $table->string('make');
                $table->string('model');
                $table->integer('year');
                $table->string('vin')->nullable(); // Vehicle Identification Number
                $table->string('status')->default('active'); // active, maintenance, retired
                $table->integer('current_mileage')->default(0);
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Maintenance Logs Table
        if (!Schema::hasTable('maintenance_logs')) {
            Schema::create('maintenance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vehicle_id');
                $table->string('service_type'); // oil_change, tire_rotation, repair
                $table->date('service_date');
                $table->integer('mileage_at_service');
                $table->decimal('cost', 15, 2);
                $table->text('notes')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Fuel Logs Table
        if (!Schema::hasTable('fuel_logs')) {
            Schema::create('fuel_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vehicle_id');
                $table->date('fill_date');
                $table->decimal('liters', 8, 2);
                $table->decimal('cost', 15, 2);
                $table->integer('mileage_at_fill');
                $table->uuid('driver_id')->nullable(); // Employee who fueled it
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
                $table->foreign('driver_id')->references('id')->on('employees')->onDelete('set null');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('vehicles');
    }
};
