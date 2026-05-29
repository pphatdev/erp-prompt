<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenants that already ran migration 066 created `base_salary` as
     * `decimal(15, 2)`. The model's EncryptedWithFallback cast writes
     * Laravel-encrypted ciphertext, which is far longer than the decimal
     * column allows. Promote the column to TEXT so encrypted writes succeed.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employee_appointments')) {
            return;
        }
        if (!Schema::hasColumn('employee_appointments', 'base_salary')) {
            return;
        }

        // Postgres cannot ALTER a column from numeric to text in-place via
        // Laravel's schema builder without doctrine/dbal, so do it with raw
        // SQL: every existing decimal value coerces cleanly to text.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employee_appointments ALTER COLUMN base_salary TYPE TEXT USING base_salary::TEXT');
        } else {
            // MySQL / SQLite path — change() needs doctrine/dbal but Laravel
            // allows TEXT redeclaration on these drivers without it.
            Schema::table('employee_appointments', function ($table) {
                $table->text('base_salary')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_appointments')) {
            return;
        }
        if (!Schema::hasColumn('employee_appointments', 'base_salary')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Best-effort rollback. Rows holding non-numeric ciphertext
            // would block the cast; null them out first so the down() can
            // succeed in development.
            DB::statement("UPDATE employee_appointments SET base_salary = NULL WHERE base_salary !~ '^[0-9]+(\.[0-9]+)?$'");
            DB::statement('ALTER TABLE employee_appointments ALTER COLUMN base_salary TYPE NUMERIC(15, 2) USING base_salary::NUMERIC');
        } else {
            Schema::table('employee_appointments', function ($table) {
                $table->decimal('base_salary', 15, 2)->nullable()->change();
            });
        }
    }
};
