<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses (Disbursement / non-AP) — pay-as-you-go spend that does NOT route
 * through Bills. Petty cash, recurring small-vendor payments, direct credit-
 * card hits, anything you'd otherwise treat as "out of pocket from the
 * company". Skips the AP step entirely:
 *
 *   Posts:        DR Expense (per line)  |  CR Cash (bank's GL)
 *   Cancellation: reverses the original JE via AccountingService::reverseEntry
 *
 * Same immutable lifecycle as bill_payments / reimbursements (posted -> cancelled).
 *
 * `supplier_id` is nullable — useful for "petty cash receipt from vendor X"
 * traceability, but the entity isn't an AP cycle so it isn't required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('expense_number', 64);

                $table->uuid('bank_account_id');
                $table->uuid('supplier_id')->nullable();

                $table->date('paid_on');
                $table->decimal('total', 15, 2);
                $table->char('currency', 3)->default('USD');

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

                $table->unique(['expense_number', 'tenant_id'], 'expenses_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('bank_account_id');
                $table->index('supplier_id');
                $table->index('paid_on');
                $table->index('status');
            });

            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->restrictOnDelete();
                $table->foreign('supplier_id')
                    ->references('id')->on('suppliers')
                    ->restrictOnDelete();
                $table->foreign('journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('reversal_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('expense_lines')) {
            Schema::create('expense_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('expense_id');
                $table->uuid('account_id');

                $table->string('description', 500)->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('receipt_attachment', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('expense_id');
                $table->index('account_id');

                $table->foreign('expense_id')
                    ->references('id')->on('expenses')
                    ->cascadeOnDelete();
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expenses');
    }
};
