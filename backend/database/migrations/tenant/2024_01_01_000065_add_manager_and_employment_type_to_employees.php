<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'manager_id')) {
                $table->uuid('manager_id')->nullable()->after('position_id');
                $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
            }
            if (!Schema::hasColumn('employees', 'employment_type')) {
                $table->string('employment_type')->default('full_time')->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'manager_id')) {
                try { $table->dropForeign(['manager_id']); } catch (\Throwable $e) {}
                $table->dropColumn('manager_id');
            }
            if (Schema::hasColumn('employees', 'employment_type')) {
                $table->dropColumn('employment_type');
            }
        });
    }
};
