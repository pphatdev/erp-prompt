<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert `employees_email_unique` from a full unique constraint to a
     * partial unique index that only enforces when `deleted_at IS NULL`.
     *
     * Why: a soft-deleted employee (terminated or post-revert) keeps its
     * `email` value on the row, but the original unique constraint covers
     * ALL rows including soft-deleted ones. That blocks legitimate rehires
     * and hire→employee conversions where the candidate's email matches a
     * historically-terminated row, surfacing as:
     *
     *     SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value
     *     violates unique constraint "employees_email_unique"
     *
     * The dedupe-by-email lookup in `RecruitmentService::convertToEmployee`
     * already uses the default Eloquent scope (excludes trashed), so the
     * intended semantics are:
     *   - email matches an ACTIVE employee → link to them (`linkedExisting`)
     *   - email matches only SOFT-DELETED rows → create a new active row
     * Making the index partial aligns the DB with that intent.
     *
     * `employees.employee_id` is intentionally NOT changed: terminated
     * employees keep their IDs forever (audit invariant). Revert handles
     * its own ID-freeing via the rename trick in
     * `RecruitmentService::revertEmployeeConversion`.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        // Drop the existing full unique constraint. Use raw SQL so the
        // migration works against the actual PG-side index name regardless
        // of whether Schema::dropUnique can resolve it on every connection.
        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_email_unique');
        DB::statement('DROP INDEX IF EXISTS employees_email_unique');

        // Recreate as a partial unique index. PostgreSQL only.
        DB::statement(
            'CREATE UNIQUE INDEX employees_email_unique ON employees (email) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS employees_email_unique');

        // Best-effort restore. Will fail if duplicate emails now exist among
        // soft-deleted rows — that's a deliberate signal: the data evolved
        // under the partial constraint and the old full constraint can no
        // longer be reasserted without first dropping duplicates.
        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_email_unique UNIQUE (email)');
    }
};
