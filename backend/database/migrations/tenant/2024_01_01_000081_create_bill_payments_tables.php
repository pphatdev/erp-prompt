<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill Payments (AP cycle continuation). Settles one or many Bills for the
 * same vendor in a single banking event.
 *
 *   Payment posts:   DR AP (per bill's payable_account_id)  |  CR Bank's GL account
 *   Cancellation:    reverses the original JE via AccountingService::reverseEntry
 *                    and decrements each linked Bill's paid_amount (downgrading
 *                    paid -> partially_paid / partially_paid -> approved).
 *
 * Payments are atomic (no draft state) — they hit the GL the moment they're
 * recorded. To correct a mistake, cancel and re-record.
 *
 * Constraint: all applications on a single payment must reference Bills of
 * the same supplier as the payment header. Enforced in the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bill_payments')) {
            Schema::create('bill_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('payment_number', 64);

                $table->uuid('bank_account_id');
                $table->uuid('supplier_id');

                $table->date('paid_on');
                $table->decimal('amount', 15, 2);
                $table->char('currency', 3)->default('USD');

                // 'bank_transfer' / 'cheque' / 'cash' / 'wire' / free string.
                $table->string('payment_method', 40)->nullable();
                $table->string('reference_number', 64)->nullable();

                // Lifecycle: posted -> cancelled. No draft (payments are immediate).
                $table->string('status', 20)->default('posted');

                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['payment_number', 'tenant_id'], 'bill_payments_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('bank_account_id');
                $table->index('supplier_id');
                $table->index('paid_on');
                $table->index('status');
            });

            Schema::table('bill_payments', function (Blueprint $table) {
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

        if (!Schema::hasTable('bill_payment_applications')) {
            Schema::create('bill_payment_applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('bill_payment_id');
                $table->uuid('bill_id');
                $table->decimal('applied_amount', 15, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('bill_payment_id');
                $table->index('bill_id');
                $table->unique(['bill_payment_id', 'bill_id'], 'bill_payment_apps_unique');

                $table->foreign('bill_payment_id')
                    ->references('id')->on('bill_payments')
                    ->cascadeOnDelete();
                $table->foreign('bill_id')
                    ->references('id')->on('bills')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payment_applications');
        Schema::dropIfExists('bill_payments');
    }
};
