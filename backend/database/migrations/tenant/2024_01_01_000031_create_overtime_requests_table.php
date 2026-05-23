<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Time Off & Attendance, Slice 3.
 *
 * Overtime requests follow the same shape as Leaves: submit → pending →
 * approved | rejected | cancelled. PayrollService reads approved rows in
 * slice 4 to add OT earnings to the relevant period's payslips.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->date('date');
                $table->decimal('hours', 5, 2);
                // Default 1.5x for normal weekday overtime; service raises to
                // 2.0x (weekend) or 3.0x (holiday) when the day lands on one.
                $table->decimal('rate_multiplier', 3, 2)->default(1.50);
                $table->text('reason')->nullable();
                $table->string('status')->default('pending');   // pending|approved|rejected|cancelled
                $table->uuid('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->index(['tenant_id', 'employee_id', 'date'], 'ot_requests_lookup_idx');
                $table->index(['tenant_id', 'status'], 'ot_requests_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
