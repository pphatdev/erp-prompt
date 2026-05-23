<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Time Off & Attendance, Slice 1.
 *
 * Introduces shift definitions plus the employee_shifts pivot that ties an
 * employee to a shift over a date range. Used by:
 *   - AttendanceService (lookup the active shift on clock-in to resolve
 *     late / half_day status against grace + threshold boundaries).
 *   - ReconcileAttendanceJob (find scheduled workdays per employee).
 *
 * The pivot's (employee_id, start_date, end_date) range supports historical
 * audit — terminated employees keep their shift history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->time('start_time');           // e.g. 08:00:00
                $table->time('end_time');             // e.g. 17:00:00
                $table->integer('grace_period_minutes')->default(0);
                $table->integer('half_day_threshold_minutes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id', 'shifts_tenant_id_idx');
            });
        }

        if (!Schema::hasTable('employee_shifts')) {
            Schema::create('employee_shifts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('shift_id');
                $table->date('start_date');
                // NULL end_date = active/current schedule (open-ended).
                $table->date('end_date')->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');

                // Hot lookup: "active shift for employee X on date Y" hits this
                // composite via (tenant_id, employee_id, start_date) range scan.
                $table->index(['tenant_id', 'employee_id', 'start_date'], 'emp_shifts_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');
    }
};
