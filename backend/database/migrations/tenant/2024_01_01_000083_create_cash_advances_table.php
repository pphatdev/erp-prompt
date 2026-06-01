<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash Advances — money given to an employee up front for upcoming expenses.
 *
 *   Issue posts:    DR Employee Advances Receivable  |  CR Cash (bank's GL)
 *   Cancellation:   reverses the issue JE (only allowed before any
 *                   settlement has been applied; otherwise reverse the
 *                   settlement(s) first).
 *
 * Settlement is a separate entity (next phase). When a settlement applies
 * actuals against this advance, it bumps `settled_amount` and rolls the
 * status forward (open -> partially_settled -> closed). Cancellation
 * never touches `settled_amount`; if any was applied the advance can't
 * be cancelled until the settlements are reversed.
 *
 * `receivable_account_id` is the GL asset account that represents the
 * advance balance on the books. Best practice: a dedicated
 * "Employee Advances Receivable" account, but the picker is open to any
 * asset account so tenants can choose what fits their CoA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_advances')) {
            Schema::create('cash_advances', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('advance_number', 64);

                $table->uuid('employee_id');
                $table->uuid('bank_account_id');
                $table->uuid('receivable_account_id');

                $table->date('issued_on');
                $table->decimal('amount', 15, 2);
                $table->decimal('settled_amount', 15, 2)->default(0);
                $table->char('currency', 3)->default('USD');

                $table->string('payment_method', 40)->nullable();
                $table->string('reference_number', 64)->nullable();
                $table->string('purpose', 500)->nullable();

                // Lifecycle: open -> partially_settled -> closed
                //         or open -> cancelled (settled_amount must be 0)
                $table->string('status', 20)->default('open');

                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['advance_number', 'tenant_id'], 'cash_advances_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('employee_id');
                $table->index('bank_account_id');
                $table->index('receivable_account_id');
                $table->index('issued_on');
                $table->index('status');
            });

            Schema::table('cash_advances', function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')->on('employees')
                    ->restrictOnDelete();
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->restrictOnDelete();
                $table->foreign('receivable_account_id')
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
        Schema::dropIfExists('cash_advances');
    }
};
