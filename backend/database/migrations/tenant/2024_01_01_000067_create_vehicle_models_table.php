<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_models')) {
            Schema::create('vehicle_models', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('make');
                $table->string('model');
                // Optional classification fields — free-text with suggestion
                // datalist on the frontend. Body: Pickup / Sedan / SUV / Van.
                // Fuel: Diesel / Gasoline / Hybrid / Electric / LPG / CNG.
                $table->string('body_type')->nullable();
                $table->string('fuel_type')->nullable();
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                // (make, model) is unique within a tenant — same vehicle catalog
                // entry can exist in different tenants without colliding.
                $table->unique(['tenant_id', 'make', 'model'], 'vehicle_models_make_model_tenant_uq');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
