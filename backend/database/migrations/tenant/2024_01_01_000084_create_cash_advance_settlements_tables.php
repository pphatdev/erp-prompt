<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash Advance Settlements — actuals applied against an open cash advance.
 *
 *   Settle posts:    DR Expense (per line)
 *                  + DR Cash    (unused returned, if any)
 *                    CR Employee Advances Receivable (= settled amount, ≤ outstanding)
 *
 *   Cancel posts:    reverses the original JE via AccountingService::reverseEntry
 *                    and decrements cash_advances.settled_amount, downgrading
 *                    the advance's status (closed → partially_settled → open)
 *                    so it returns to its prior state.
 *
 * `actual_amount` is the real money spent on the activity. The lines sum to
 * `actual_amount`. If the employee returns leftover cash, `unused_returned`
 * captures that — `actual_amount = sum(lines)` and the receivable credit equals
 * `actual_amount - unused_returned` (the amount this settlement clears off the
 * advance receivable). When `unused_returned > 0`, `bank_account_id` is
 * required so the cash hit lands on a real bank GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_advance_settlements')) {
            Schema::create('cash_advance_settlements', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('settlement_number', 64);

                $table->uuid('cash_advance_id');
                $table->uuid('bank_account_id')->nullable(); // required when unused_returned > 0

                $table->date('settled_on');
                $table->decimal('actual_amount', 15, 2);
                $table->decimal('unused_returned', 15, 2)->default(0);

                $table->string('payment_method', 40)->nullable();
                $table->string('reference_number', 64)->nullable();

                // Lifecycle: posted -> cancelled.
                $table->string('status', 20)->default('posted');

                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['settlement_number', 'tenant_id'], 'cash_advance_settlements_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('cash_advance_id');
                $table->index('bank_account_id');
                $table->index('settled_on');
                $table->index('status');
            });

            Schema::table('cash_advance_settlements', function (Blueprint $table) {
                $table->foreign('cash_advance_id')
                    ->references('id')->on('cash_advances')
                    ->restrictOnDelete();
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->restrictOnDelete();
                $table->foreign('journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('reversal_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cash_advance_settlement_lines')) {
            Schema::create('cash_advance_settlement_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('settlement_id');
                $table->uuid('account_id');

                $table->string('description', 500)->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('receipt_attachment', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('settlement_id');
                $table->index('account_id');

                $table->foreign('settlement_id')
                    ->references('id')->on('cash_advance_settlements')
                    ->cascadeOnDelete();
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_settlement_lines');
        Schema::dropIfExists('cash_advance_settlements');
    }
};
