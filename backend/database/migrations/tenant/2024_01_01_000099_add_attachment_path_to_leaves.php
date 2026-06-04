<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Leave & Time Off policy enforcement.
 *
 * Adds a nullable `attachment_path` column to `leaves` so the
 * `hrm.leave.attachment_required_days` setting can enforce supporting
 * documents (e.g. medical certificates) on long requests. Value is a
 * relative path inside the tenant-scoped filesystem — never a public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leaves') && !Schema::hasColumn('leaves', 'attachment_path')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->string('attachment_path', 512)->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leaves') && Schema::hasColumn('leaves', 'attachment_path')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->dropColumn('attachment_path');
            });
        }
    }
};
