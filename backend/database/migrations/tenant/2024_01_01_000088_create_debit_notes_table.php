<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Debit Notes (AR cycle adjustment, opposite direction of CreditNote).
 *
 * Issued when the company has under-billed the customer — additional charges
 * that need to go on the customer's AR after the original invoice closed.
 *
 *   Issue posts:    DR Accounts Receivable
 *                   CR Revenue (or whatever income account)
 *   Cancellation:   reverses the original JE via AccountingService::reverseEntry
 *
 * `invoice_id` is nullable and used for **traceability only** — debit notes
 * do NOT modify the linked invoice's paid_amount or status. They stand as
 * their own AR balance, settled by a future Receipt. This is the opposite
 * of CreditNote, which folds into the linked invoice's paid_amount.
 *
 * Immutable once issued (`issued` -> `cancelled` only).
 * Single-amount entity — no lines table (matches the spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('debit_notes')) {
            Schema::create('debit_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('debit_note_number', 64);

                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('revenue_account_id');
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

                $table->unique(['debit_note_number', 'tenant_id'], 'debit_notes_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('customer_id');
                $table->index('invoice_id');
                $table->index('revenue_account_id');
                $table->index('ar_account_id');
                $table->index('issue_date');
                $table->index('status');
            });

            Schema::table('debit_notes', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')->on('customers')
                    ->restrictOnDelete();
                $table->foreign('invoice_id')
                    ->references('id')->on('invoices')
                    ->restrictOnDelete();
                $table->foreign('revenue_account_id')
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
        Schema::dropIfExists('debit_notes');
    }
};
