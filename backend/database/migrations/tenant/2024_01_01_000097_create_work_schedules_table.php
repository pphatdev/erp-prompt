<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 10 - Hierarchical Working Days/Hours Setting.
 *
 * `work_schedules` holds one row per (target, day-of-week) combination. The
 * resolver walks Employee -> Department -> Global to find the most specific
 * schedule for a given date, so a tenant can declare a company default once
 * and then override per department (e.g. branch on a different week) or per
 * employee (e.g. part-time engineer).
 *
 *   target_type ∈ {global, department, employee}
 *   target_id   ∈ NULL for global, UUID for department/employee
 *   day_of_week ∈ 1..7 (ISO: Monday=1 .. Sunday=7)
 *   is_work_day ∈ true|false
 *   intervals   ∈ JSON array of {start, end} time strings (HH:MM, 24h)
 *
 * Unique on (tenant_id, target_type, target_id, day_of_week) so the same
 * target can't carry two rows for the same weekday. Soft-deletes preserved
 * for audit / revert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_schedules')) {
            return;
        }

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // global / department / employee — kept as a free string to avoid
            // a PG enum migration; the model + service validate the value.
            $table->string('target_type', 20);
            // NULL for global rows. We can't FK this because the column points
            // at different tables; the service validates the target exists.
            $table->uuid('target_id')->nullable();

            $table->unsignedTinyInteger('day_of_week'); // 1..7 (Mon..Sun)
            $table->boolean('is_work_day')->default(true);

            // Array of { start: 'HH:MM', end: 'HH:MM' } sorted by start.
            // Empty array on non-work days. Multiple intervals support
            // split shifts (e.g. 08:00-12:00 + 13:00-17:00).
            $table->jsonb('intervals')->default('[]');

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['target_type', 'target_id']);
            // The resolver does (target_type, target_id, day_of_week) lookups
            // for each date being counted — keep this dense.
            $table->unique(
                ['tenant_id', 'target_type', 'target_id', 'day_of_week'],
                'work_schedules_target_dow_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
