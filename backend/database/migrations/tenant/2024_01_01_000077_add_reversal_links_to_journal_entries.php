<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL self-FK pattern: add columns first, then constrain in a
        // second pass so each side resolves cleanly.
        Schema::table('journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entries', 'reverses_journal_id')) {
                $table->uuid('reverses_journal_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('journal_entries', 'reversed_by_journal_id')) {
                $table->uuid('reversed_by_journal_id')->nullable()->after('reverses_journal_id');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_journal_id')
                ->references('id')->on('journal_entries')
                ->nullOnDelete();
            $table->foreign('reversed_by_journal_id')
                ->references('id')->on('journal_entries')
                ->nullOnDelete();

            $table->index('reverses_journal_id');
            $table->index('reversed_by_journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reverses_journal_id']);
            $table->dropForeign(['reversed_by_journal_id']);
            $table->dropIndex(['reverses_journal_id']);
            $table->dropIndex(['reversed_by_journal_id']);
            $table->dropColumn(['reverses_journal_id', 'reversed_by_journal_id']);
        });
    }
};
