<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the new ciphertext column alongside the legacy decimal one,
        //    plus the bank fields. All four hold Laravel-encrypted strings.
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'base_salary_encrypted')) {
                $table->text('base_salary_encrypted')->nullable()->after('hired_at');
            }
            if (!Schema::hasColumn('employees', 'bank_name')) {
                $table->text('bank_name')->nullable()->after('base_salary_encrypted');
            }
            if (!Schema::hasColumn('employees', 'bank_account_name')) {
                $table->text('bank_account_name')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('employees', 'bank_account_number')) {
                $table->text('bank_account_number')->nullable()->after('bank_account_name');
            }
        });

        // 2. Encrypt existing plaintext base_salary values into the new column.
        DB::table('employees')
            ->whereNotNull('base_salary')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('employees')
                        ->where('id', $row->id)
                        ->update([
                            'base_salary_encrypted' => Crypt::encryptString((string) $row->base_salary),
                        ]);
                }
            });

        // 3. Drop the legacy decimal column, then promote the encrypted column.
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'base_salary')) {
                $table->dropColumn('base_salary');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('base_salary_encrypted', 'base_salary');
        });
    }

    public function down(): void
    {
        // 1. Re-create a decimal staging column.
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'base_salary_decimal')) {
                $table->decimal('base_salary_decimal', 15, 2)->nullable()->after('hired_at');
            }
        });

        // 2. Decrypt back into plaintext decimal. Rows whose ciphertext fails to
        //    decrypt (e.g. APP_KEY rotated) are skipped — preferable to losing
        //    them via an unhandled exception during rollback.
        DB::table('employees')
            ->whereNotNull('base_salary')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $plain = Crypt::decryptString($row->base_salary);
                    } catch (\Throwable) {
                        continue;
                    }
                    DB::table('employees')
                        ->where('id', $row->id)
                        ->update(['base_salary_decimal' => (float) $plain]);
                }
            });

        // 3. Drop the encrypted columns, then rename the staging column back.
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['base_salary', 'bank_name', 'bank_account_name', 'bank_account_number']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('base_salary_decimal', 'base_salary');
        });
    }
};
