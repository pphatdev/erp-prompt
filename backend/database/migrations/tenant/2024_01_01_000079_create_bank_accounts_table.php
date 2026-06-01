<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank accounts — specialized view of asset-type Accounts representing
 * physical cash and bank deposits. Acts as the foundation for:
 *   - Receipt Payment   (DR Cash / CR AR)         — needs bank_account_id
 *   - Pay Bill          (DR AP / CR Cash)         — needs bank_account_id
 *   - Reimbursement     (DR Expense / CR Cash)    — needs bank_account_id
 *   - Expense           (DR Expense / CR Cash)    — needs bank_account_id
 *
 * Book balance is NOT stored on this table — it's read live from the linked
 * `accounts.balance` column to avoid drift. `opening_balance` and
 * `last_reconciled_balance` are reconciliation reference points only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Link to the GL account in the Chart of Accounts. Nullable so
                // a bank entry can be drafted before the CoA is fully built;
                // service-layer warns when missing.
                $table->uuid('account_id')->nullable();

                $table->string('name', 160);
                $table->string('bank_name', 160);
                $table->string('branch', 160)->nullable();
                $table->string('account_number', 60)->nullable();
                $table->string('account_holder', 160)->nullable();
                $table->string('swift', 20)->nullable();
                $table->string('iban', 40)->nullable();
                $table->char('currency', 3)->default('USD');

                // Reconciliation reference points (NOT a substitute for the GL
                // balance, which always wins).
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->timestamp('last_reconciled_at')->nullable();
                $table->decimal('last_reconciled_balance', 15, 2)->nullable();

                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('account_id');
                $table->index('is_active');
                $table->index('is_default');
                $table->index('currency');
            });

            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
