<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Credit Notes (AR cycle adjustment). Customer-side mirror of a
 * reversal — issued for returns, billing errors, or goodwill credits.
 *
 *   Issue posts:    DR Sales Returns (or other contra-revenue / expense)
 *                   CR Accounts Receivable
 *   Cancellation:   reverses the original JE via AccountingService::reverseEntry
 *                   and decrements the linked invoice's paid_amount (when linked).
 *
 * `invoice_id` is nullable — credits can be unlinked (carried as a standing
 * customer credit) or linked to a specific invoice. When linked, the credit
 * amount is rolled into invoice.paid_amount alongside any receipts, so a
 * confirmed invoice can be closed by credit notes alone.
 *
 * Immutable once issued (`issued` -> `cancelled` only). No draft state.
 * Single-amount entity — no `credit_note_lines` table (matches the spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('credit_note_number', 64);

                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('sales_returns_account_id');
                $table->uuid('ar_account_id');

                $table->date('issue_date');
                $table->decimal('amount', 15, 2);
                $table->char('currency', 3)->default('USD');

                $table->string('reason', 500);

                // Lifecycle: issued -> cancelled.
                $table->string('status', 20)->default('issued');

                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['credit_note_number', 'tenant_id'], 'credit_notes_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('customer_id');
                $table->index('invoice_id');
                $table->index('sales_returns_account_id');
                $table->index('ar_account_id');
                $table->index('issue_date');
                $table->index('status');
            });

            Schema::table('credit_notes', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')->on('customers')
                    ->restrictOnDelete();
                $table->foreign('invoice_id')
                    ->references('id')->on('invoices')
                    ->restrictOnDelete();
                $table->foreign('sales_returns_account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
                $table->foreign('ar_account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
                $table->foreign('journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('reversal_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
