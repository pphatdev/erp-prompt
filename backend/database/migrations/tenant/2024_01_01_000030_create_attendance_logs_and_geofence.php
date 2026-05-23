<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Time Off & Attendance, Slice 2.
 *
 * Adds geofence + IP whitelist columns to `departments` so attendance
 * clock-in can validate location and network against the office on file,
 * and creates the `attendance_logs` table for the resolved daily records.
 *
 * NULL latitude/longitude on a department means "no geofence enforcement"
 * for employees in that department — useful for remote-first orgs that
 * still want to track late/early-out vs. shift hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (!Schema::hasColumn('departments', 'latitude')) {
                    $table->decimal('latitude', 10, 8)->nullable()->after('code');
                }
                if (!Schema::hasColumn('departments', 'longitude')) {
                    $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
                }
                if (!Schema::hasColumn('departments', 'geofence_radius_meters')) {
                    // Default 100m matches the spec; only enforced when
                    // latitude+longitude are both set on the department.
                    $table->integer('geofence_radius_meters')->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('departments', 'attendance_ip_whitelist')) {
                    // JSON array of CIDR ranges (e.g. ["10.0.0.0/8", "192.168.1.0/24"]).
                    // NULL = no IP enforcement.
                    $table->json('attendance_ip_whitelist')->nullable();
                }
            });
        }

        if (!Schema::hasTable('attendance_logs')) {
            Schema::create('attendance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                // Resolved calendar date (timezone-normalised by the service).
                $table->date('date');
                $table->timestamp('check_in')->nullable();
                $table->timestamp('check_out')->nullable();
                // present, late, early_out, half_day, absent,
                // paid_leave, unpaid_leave, weekend, holiday.
                $table->string('status')->default('present');

                // Audit fields — stored even when geofence is not enforced
                // so admins can investigate retroactively.
                $table->string('check_in_ip', 45)->nullable();
                $table->string('check_out_ip', 45)->nullable();
                $table->decimal('check_in_lat', 10, 8)->nullable();
                $table->decimal('check_in_lon', 11, 8)->nullable();
                $table->decimal('check_out_lat', 10, 8)->nullable();
                $table->decimal('check_out_lon', 11, 8)->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->index('tenant_id', 'att_logs_tenant_idx');
            });

            // Partial unique: one live row per (employee, date). Soft-deleted
            // rows don't block re-creation so admins can correct mistakes
            // by trashing the bad row first. Uses raw SQL because Schema
            // doesn't expose partial indexes directly.
            DB::statement('CREATE UNIQUE INDEX att_logs_emp_date_uidx ON attendance_logs(employee_id, date) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_logs')) {
            DB::statement('DROP INDEX IF EXISTS att_logs_emp_date_uidx');
            Schema::dropIfExists('attendance_logs');
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                foreach (['attendance_ip_whitelist', 'geofence_radius_meters', 'longitude', 'latitude'] as $col) {
                    if (Schema::hasColumn('departments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
