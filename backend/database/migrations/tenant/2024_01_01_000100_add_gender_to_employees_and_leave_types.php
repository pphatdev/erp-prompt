<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Gender-aware Leave Types.
 *
 * Adds:
 *   - employees.gender (nullable string, 16) — 'male' | 'female' | 'other'.
 *     Optional per the data-collection spec §3.1; non-sensitive.
 *   - leave_types.applicable_gender (string(8) default 'any') — gates which
 *     employees can submit leaves of this type. LeaveService enforces on
 *     submitRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'gender')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('gender', 16)->nullable()->after('email');
            });
        }

        if (Schema::hasTable('leave_types') && !Schema::hasColumn('leave_types', 'applicable_gender')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->string('applicable_gender', 8)->default('any')->after('annual_allowance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'applicable_gender')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('applicable_gender');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'gender')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }
};
