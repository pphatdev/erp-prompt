<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Receipts (AR cycle continuation). Settles one or many Invoices for
 * the same customer in a single banking event — the AR-side mirror of
 * bill_payments.
 *
 *   Receipt posts:   DR Bank's GL account  |  CR AR (per receipt header ar_account)
 *   Cancellation:    reverses the original JE via AccountingService::reverseEntry
 *                    and decrements each linked Invoice's paid_amount (downgrading
 *                    paid -> confirmed when no longer fully paid).
 *
 * Receipts are atomic (no draft state). To correct, cancel and re-record.
 *
 * Constraint: all applications on a single receipt must reference Invoices of
 * the same customer as the receipt header. Enforced in the service.
 *
 * `ar_account_id` is captured at the receipt-header level (rather than per
 * application) because the source-of-truth AR account is tenant-level
 * (resolved by InvoiceService via `fms.ar_account_code`), and every CR line
 * lands on it. Service validates it's an asset-type account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('receipt_number', 64);

                $table->uuid('customer_id');
                $table->uuid('bank_account_id');
                $table->uuid('ar_account_id');

                $table->date('received_on');
                $table->decimal('amount', 15, 2);
                $table->char('currency', 3)->default('USD');

                $table->string('payment_method', 40)->nullable();
                $table->string('reference_number', 64)->nullable();

                // Lifecycle: posted -> cancelled. No draft.
                $table->string('status', 20)->default('posted');

                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['receipt_number', 'tenant_id'], 'receipts_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('customer_id');
                $table->index('bank_account_id');
                $table->index('ar_account_id');
                $table->index('received_on');
                $table->index('status');
            });

            Schema::table('receipts', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')->on('customers')
                    ->restrictOnDelete();
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
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

        if (!Schema::hasTable('receipt_invoice_applications')) {
            Schema::create('receipt_invoice_applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('receipt_id');
                $table->uuid('invoice_id');
                $table->decimal('applied_amount', 15, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('receipt_id');
                $table->index('invoice_id');
                $table->unique(['receipt_id', 'invoice_id'], 'receipt_invoice_apps_unique');

                $table->foreign('receipt_id')
                    ->references('id')->on('receipts')
                    ->cascadeOnDelete();
                $table->foreign('invoice_id')
                    ->references('id')->on('invoices')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_invoice_applications');
        Schema::dropIfExists('receipts');
    }
};
