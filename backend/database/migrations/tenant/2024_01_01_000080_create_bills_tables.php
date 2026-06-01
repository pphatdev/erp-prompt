<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills (AP cycle) — supplier invoices booked into Accounts Payable.
 *
 *   Bill approval posts:  DR Expense/Asset (per line)  |  CR AP (control account)
 *   Bill cancellation:    reverses the approval JE via AccountingService::reverseEntry
 *   Pay Bill (next):      DR AP  |  CR Cash, updates paid_amount per bill
 *
 * A Bill is distinct from a Purchase Order: the PO is a commitment, the Bill
 * is the obligation that lands on the AP ledger. A Bill may reference a PO
 * (po_id) when matching three-way, but the link is optional.
 *
 * Defaults for `payable_account_id` resolve from `suppliers.default_payable_account_id`
 * (shipped via Vendor extension, migration 000078) so accountants don't have
 * to pick AP on every bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bills')) {
            Schema::create('bills', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Bill identity
                $table->string('bill_number', 64);
                $table->string('supplier_invoice_number', 64)->nullable();

                // Counterparties
                $table->uuid('supplier_id');
                $table->uuid('po_id')->nullable();

                // Dates + currency
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->char('currency', 3)->default('USD');

                // Money
                $table->decimal('subtotal',    15, 2)->default(0);
                $table->decimal('tax_amount',  15, 2)->default(0);
                $table->decimal('total',       15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);

                // Lifecycle: draft -> approved -> (partially_paid -> paid) | cancelled
                $table->string('status', 20)->default('draft');

                // GL linkage
                $table->uuid('payable_account_id')->nullable();
                $table->uuid('journal_entry_id')->nullable();
                $table->uuid('reversal_journal_entry_id')->nullable();

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['bill_number', 'tenant_id'], 'bills_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('supplier_id');
                $table->index('po_id');
                $table->index('status');
                $table->index('due_date');
            });

            Schema::table('bills', function (Blueprint $table) {
                $table->foreign('supplier_id')
                    ->references('id')->on('suppliers')
                    ->restrictOnDelete();
                $table->foreign('po_id')
                    ->references('id')->on('purchase_orders')
                    ->nullOnDelete();
                $table->foreign('payable_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
                $table->foreign('journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('reversal_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('bill_lines')) {
            Schema::create('bill_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('bill_id');
                $table->uuid('account_id');

                $table->string('description', 500)->nullable();
                $table->decimal('quantity',   12, 3)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('bill_id');
                $table->index('account_id');

                $table->foreign('bill_id')
                    ->references('id')->on('bills')
                    ->cascadeOnDelete();
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
    }
};
