<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reimbursements (Disbursement / AP) — paying an employee back for an
 * out-of-pocket expense. Skips the Bill/AP step entirely:
 *
 *   Posts:        DR Expense (per line)  |  CR Cash (bank's GL)
 *   Cancellation: reverses the original JE via AccountingService::reverseEntry
 *
 * Same lifecycle shape as bill_payments — immediate (no draft), then either
 * `posted` or `cancelled`. Receipt attachments are stored as a relative
 * path on each line (TBD which uploader; nullable for now).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reimbursements')) {
            Schema::create('reimbursements', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('reimbursement_number', 64);

                $table->uuid('employee_id');
                $table->uuid('bank_account_id');

                $table->date('paid_on');
                $table->decimal('amount', 15, 2);
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

                $table->unique(['reimbursement_number', 'tenant_id'], 'reimbursements_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('employee_id');
                $table->index('bank_account_id');
                $table->index('paid_on');
                $table->index('status');
            });

            Schema::table('reimbursements', function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')->on('employees')
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

        if (!Schema::hasTable('reimbursement_lines')) {
            Schema::create('reimbursement_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('reimbursement_id');
                $table->uuid('account_id');

                $table->string('description', 500)->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('receipt_attachment', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('reimbursement_id');
                $table->index('account_id');

                $table->foreign('reimbursement_id')
                    ->references('id')->on('reimbursements')
                    ->cascadeOnDelete();
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_lines');
        Schema::dropIfExists('reimbursements');
    }
};
