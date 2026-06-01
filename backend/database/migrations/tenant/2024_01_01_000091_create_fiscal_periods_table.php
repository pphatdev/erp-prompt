<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal Periods. A period is a named date range. Open periods accept new
 * journal entries; locked periods refuse them via AccountingService::postEntry.
 *
 * Lifecycle: open -> locked. Reopen requires a separate
 * `fms.fiscal_periods.reopen` permission and intentionally leaves the
 * closing journal entry in place so that any reversal is explicit.
 *
 * `retained_earnings_account_id` is captured at close time so the closing
 * balance rollover is auditable. `closing_journal_entry_id` records the
 * JE that zeroed revenue and expense accounts into Retained Earnings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fiscal_periods')) {
            Schema::create('fiscal_periods', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('period_number', 64);
                $table->string('name', 200);

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 20)->default('open');

                $table->timestamp('locked_at')->nullable();
                $table->uuid('locked_by')->nullable();

                $table->uuid('retained_earnings_account_id')->nullable();
                $table->uuid('closing_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['period_number', 'tenant_id'], 'fiscal_periods_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('status');
                $table->index('start_date');
                $table->index('end_date');
                // Composite for the write-block lookup: range scan filtered by status.
                $table->index(['tenant_id', 'status', 'start_date', 'end_date'], 'fiscal_periods_lock_lookup_idx');
            });

            Schema::table('fiscal_periods', function (Blueprint $table) {
                $table->foreign('retained_earnings_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
                $table->foreign('closing_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
