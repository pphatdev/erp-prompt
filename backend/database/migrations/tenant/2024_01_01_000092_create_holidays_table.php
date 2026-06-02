<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holidays - public, company, and optional days off.
 *
 * `is_recurring` flags a yearly anchor: the `date` becomes the canonical
 * month/day and HolidayService expands it across requested ranges. e.g. a
 * recurring entry dated 2026-01-01 fires every Jan 1 going forward.
 *
 * Types:
 *   public   - statutory / national holiday, paid by default
 *   company  - org-defined day off (founder's day, blackout, etc.)
 *   optional - religious / regional, employee may opt in/out
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 200);
                $table->date('date');
                $table->string('type', 20)->default('public');
                $table->boolean('is_recurring')->default(false);

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('date');
                $table->index('type');
                // A given calendar date can carry multiple distinct holidays
                // (e.g. New Year + cultural overlay), so the unique guard is
                // on (tenant_id, date, name) rather than (tenant_id, date).
                $table->unique(['tenant_id', 'date', 'name'], 'holidays_tenant_date_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
