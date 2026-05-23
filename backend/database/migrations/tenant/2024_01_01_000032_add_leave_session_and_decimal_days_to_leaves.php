<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM — Time Off & Attendance, Slice 5.
 *
 * Enables half-day leave on the existing `leaves` table:
 *   - new column `leave_session` (full_day | morning | afternoon)
 *   - widens `days` from integer to decimal(4,1) so 0.5 fits without
 *     rounding-collisions across approved/pending balance sums.
 *
 * Backfills existing rows: leave_session defaults to `full_day`, and
 * integer day counts cast cleanly to decimal (no data loss).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leaves')) {
            // Widen days. Postgres-specific raw cast — alterTable($table) on
            // doctrine/dbal would refuse without the schema diff hint.
            DB::statement('ALTER TABLE leaves ALTER COLUMN days TYPE NUMERIC(4,1) USING days::numeric(4,1)');

            Schema::table('leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('leaves', 'leave_session')) {
                    // full_day | morning | afternoon — service validates that
                    // morning / afternoon implies days == 0.5.
                    $table->string('leave_session', 16)->default('full_day')->after('days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leaves')) {
            Schema::table('leaves', function (Blueprint $table) {
                if (Schema::hasColumn('leaves', 'leave_session')) {
                    $table->dropColumn('leave_session');
                }
            });

            // Round half-day rows up to 1 before narrowing the column so the
            // cast is data-preserving for the dominant case (full-day rows).
            DB::statement('UPDATE leaves SET days = CEIL(days) WHERE days <> FLOOR(days)');
            DB::statement('ALTER TABLE leaves ALTER COLUMN days TYPE INTEGER USING ROUND(days)::integer');
        }
    }
};
