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
        // Payroll Periods Table
        if (!Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name'); // e.g., "May 2026"
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('draft'); // draft, processed, closed
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index('tenant_id');
            });
        }

        // Payslips Table
        if (!Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('payroll_period_id');
                $table->uuid('employee_id');
                $table->decimal('gross_salary', 15, 2);
                $table->decimal('net_salary', 15, 2);
                $table->json('earnings')->nullable();
                $table->json('deductions')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
    }
};
