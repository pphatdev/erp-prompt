<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar and Holiday Management - Phase 1 schema.
 *
 * Builds on the existing `holidays` table (created in 000092 for HRM) and
 * adds:
 *
 *   ALTER holidays
 *     overtime_multiplier  decimal(4,2) default 3.00 - feeds payroll holiday
 *                          rate calc (Holiday Rate = Hourly Rate * multiplier).
 *     branch_id            uuid nullable - reserved for per-region holiday
 *                          scoping. No FK enforced until a Branch model lands.
 *
 *   CREATE calendar_events - Custom tenant events ("All-hands", "Town hall",
 *                            training). Per v1 scope decision, leaves /
 *                            shifts / CRM appointments are NOT mirrored into
 *                            this table - CalendarEventService unions them
 *                            on-the-fly from their source tables at query
 *                            time. The eventable_type/id columns are reserved
 *                            for future per-entity attachments (e.g. a meeting
 *                            anchored to a CRM lead).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('holidays')) {
            Schema::table('holidays', function (Blueprint $table) {
                if (!Schema::hasColumn('holidays', 'overtime_multiplier')) {
                    $table->decimal('overtime_multiplier', 4, 2)->default(3.00)->after('is_recurring');
                }
                if (!Schema::hasColumn('holidays', 'branch_id')) {
                    $table->uuid('branch_id')->nullable()->after('overtime_multiplier');
                    $table->index(['tenant_id', 'branch_id'], 'holidays_branch_idx');
                }
            });
        }

        if (!Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->timestamp('start_time');
                $table->timestamp('end_time');
                // category: general | meeting | training | company | personal
                $table->string('category', 30)->default('general');
                $table->boolean('is_all_day')->default(false);
                $table->uuid('employee_id')->nullable();
                // Reserved for future polymorphic attachment (e.g. crm lead, project task).
                $table->string('eventable_type', 120)->nullable();
                $table->uuid('eventable_id')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();

                $table->index(['tenant_id', 'start_time'], 'calendar_events_start_idx');
                $table->index(['tenant_id', 'employee_id'], 'calendar_events_emp_idx');
                $table->index(['tenant_id', 'category'], 'calendar_events_category_idx');
                $table->index(['eventable_type', 'eventable_id'], 'calendar_events_eventable_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        if (Schema::hasTable('holidays')) {
            Schema::table('holidays', function (Blueprint $table) {
                if (Schema::hasColumn('holidays', 'branch_id')) {
                    $table->dropIndex('holidays_branch_idx');
                    $table->dropColumn('branch_id');
                }
                if (Schema::hasColumn('holidays', 'overtime_multiplier')) {
                    $table->dropColumn('overtime_multiplier');
                }
            });
        }
    }
};
