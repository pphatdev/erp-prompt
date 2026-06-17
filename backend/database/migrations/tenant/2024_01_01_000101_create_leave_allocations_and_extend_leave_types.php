<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 - Leave & Time Off Settings (Allocations & Calculation).
 *
 * 1. Extends `leave_types` with classification fields:
 *    - `code` (unique per tenant, short identifier like VAC, SICK, MAT)
 *    - `is_paid` (paid vs unpaid leave bucket — Unpaid Leave = false)
 *    - `is_accrued` (monthly accrual vs lump-sum allocation — Annual = true)
 *
 * 2. Creates `employee_leave_allocations` — the per-employee, per-type,
 *    per-year ledger that replaces the on-the-fly accrual math. Holds
 *    allocated / used / pending day counters so balance reads are O(1)
 *    and so half-day decimals round-trip cleanly.
 *
 * Unique on (tenant_id, employee_id, leave_type_id, year) so an employee
 * gets exactly one row per type per year.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table) {
                if (!Schema::hasColumn('leave_types', 'code')) {
                    $table->string('code', 32)->nullable()->after('name');
                }
                if (!Schema::hasColumn('leave_types', 'is_paid')) {
                    $table->boolean('is_paid')->default(true)->after('annual_allowance');
                }
                if (!Schema::hasColumn('leave_types', 'is_accrued')) {
                    // Accrued = monthly proration; non-accrued = lump-sum
                    // available on the first of the year (or hire date).
                    $table->boolean('is_accrued')->default(false)->after('is_paid');
                }
            });

            // Composite unique on (tenant_id, code) so each tenant can keep
            // its own catalogue (codes don't have to be globally unique
            // across tenants — same DB-per-tenant arrangement means each
            // tenant's leave_types table is isolated, but the constraint
            // catches accidental duplicates inside a tenant).
            $hasUniqueIndex = collect(\DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'leave_types'"))
                ->contains(fn ($row) => $row->indexname === 'leave_types_tenant_id_code_unique');
            if (!$hasUniqueIndex) {
                Schema::table('leave_types', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'code'], 'leave_types_tenant_id_code_unique');
                });
            }
        }

        if (!Schema::hasTable('employee_leave_allocations')) {
            Schema::create('employee_leave_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('leave_type_id');
                // Smallint is plenty for a 4-digit year; saves 6 bytes/row
                // over INT at scale.
                $table->smallInteger('year');
                // (6,2) gives us 9999.99 max — comfortable headroom for
                // a multi-year carryover total.
                $table->decimal('allocated_days', 6, 2)->default(0);
                $table->decimal('used_days',      6, 2)->default(0);
                $table->decimal('pending_days',   6, 2)->default(0);
                $table->string('note', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
                $table->index('tenant_id');
                $table->index(['employee_id', 'year']);
                $table->unique(
                    ['tenant_id', 'employee_id', 'leave_type_id', 'year'],
                    'leave_allocations_unique_per_year'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_allocations');

        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $hasUnique = collect(\DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'leave_types'"))
                    ->contains(fn ($row) => $row->indexname === 'leave_types_tenant_id_code_unique');
                if ($hasUnique) {
                    $table->dropUnique('leave_types_tenant_id_code_unique');
                }
                if (Schema::hasColumn('leave_types', 'is_accrued')) {
                    $table->dropColumn('is_accrued');
                }
                if (Schema::hasColumn('leave_types', 'is_paid')) {
                    $table->dropColumn('is_paid');
                }
                if (Schema::hasColumn('leave_types', 'code')) {
                    $table->dropColumn('code');
                }
            });
        }
    }
};
