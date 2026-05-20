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
        // Leave Types Table
        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->integer('annual_allowance')->default(0);
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index('tenant_id');
            });
        }

        // Leaves Table
        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('leave_type_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('days');
                $table->text('reason')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
    }
};
