<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->uuid('lead_id')->nullable();
            $table->uuid('customer_id');
            $table->string('stage')->default('new');
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('close_date')->nullable();
            $table->text('loss_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
