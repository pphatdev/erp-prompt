<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank Reconciliation Sessions.
 *
 * One session reconciles a bank statement period for a single bank account.
 *
 *   Open:    user records statement_ending_balance + start/end dates.
 *            opening_balance defaults to bank_account.last_reconciled_balance.
 *   Match:   user pairs each statement line with a posted ledger_entry on the
 *            bank's GL account in the period. 1:1 only (v1).
 *   Close:   refused unless every statement line is matched AND
 *              opening_balance + sum(statement_line.amount) == statement_ending_balance.
 *            On close: updates bank_account.last_reconciled_at / last_reconciled_balance.
 *
 * Sessions are immutable once closed. Reopen requires a separate
 * `fms.bank_recon.reopen` permission.
 *
 * Statement line `amount` is signed:
 *   - Positive = deposit (your books DR Cash, bank's GL debit > 0)
 *   - Negative = withdrawal (your books CR Cash, bank's GL credit > 0)
 *
 * `matched_ledger_entry_id` is null when the line is unmatched. When set,
 * service validates the ledger entry is on the session's bank GL account and
 * its debit/credit sign agrees with the statement line direction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_recon_sessions')) {
            Schema::create('bank_recon_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('session_number', 64);

                $table->uuid('bank_account_id');

                $table->date('start_date');
                $table->date('end_date');

                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('statement_ending_balance', 15, 2);
                $table->decimal('book_ending_balance', 15, 2)->default(0);

                // Lifecycle: open -> closed.
                $table->string('status', 20)->default('open');

                $table->timestamp('closed_at')->nullable();
                $table->uuid('closed_by')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['session_number', 'tenant_id'], 'bank_recon_sessions_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('bank_account_id');
                $table->index('start_date');
                $table->index('end_date');
                $table->index('status');
            });

            Schema::table('bank_recon_sessions', function (Blueprint $table) {
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->restrictOnDelete();
                // closed_by references users.id but the users FK convention is to
                // leave it free of constraint (matches Invoice.confirmed_by pattern).
            });
        }

        if (!Schema::hasTable('bank_recon_statement_lines')) {
            Schema::create('bank_recon_statement_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('session_id');

                $table->date('statement_date');
                $table->string('description', 500);
                $table->string('reference_number', 64)->nullable();

                // Signed: positive = deposit, negative = withdrawal.
                $table->decimal('amount', 15, 2);

                $table->uuid('matched_ledger_entry_id')->nullable();

                $table->string('notes', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('session_id');
                $table->index('matched_ledger_entry_id');
                $table->index('statement_date');

                $table->foreign('session_id')
                    ->references('id')->on('bank_recon_sessions')
                    ->cascadeOnDelete();
                $table->foreign('matched_ledger_entry_id')
                    ->references('id')->on('ledger_entries')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_recon_statement_lines');
        Schema::dropIfExists('bank_recon_sessions');
    }
};
