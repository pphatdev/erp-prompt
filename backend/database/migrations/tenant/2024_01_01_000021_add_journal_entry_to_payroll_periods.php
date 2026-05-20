<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_periods', 'journal_entry_id')) {
                $table->uuid('journal_entry_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payroll_periods', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('journal_entry_id');
            }
        });

        // NB: no FK constraint — payroll_periods and journal_entries can live
        // in the same tenant schema, but enforcing a hard FK would tie payroll
        // close lifecycle to FMS schema availability during fresh setup.
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropIndex(['journal_entry_id']);
            $table->dropColumn(['journal_entry_id', 'closed_at']);
        });
    }
};
