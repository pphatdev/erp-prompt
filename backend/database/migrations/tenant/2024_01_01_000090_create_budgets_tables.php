<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets and budget lines.
 *
 * A Budget is a named target for a date range. Each line pins an
 * expected amount to an Account. Variance is computed at read time
 * against posted ledger_entries within (start_date, end_date).
 *
 * Lifecycle: draft (editable) -> active (locked) -> archived (locked).
 *
 * Variance semantics, per Account natural balance:
 *   expense, asset       -> actual = sum(debit) - sum(credit) in period
 *   revenue, liability,  -> actual = sum(credit) - sum(debit) in period
 *   equity
 *
 * Reversed journal entries are excluded from actuals to avoid double counting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('budget_number', 64);
                $table->string('name', 200);

                $table->date('start_date');
                $table->date('end_date');

                // Lifecycle: draft -> active -> archived.
                $table->string('status', 20)->default('draft');

                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['budget_number', 'tenant_id'], 'budgets_number_tenant_unique');
                $table->index('tenant_id');
                $table->index('start_date');
                $table->index('end_date');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('budget_lines')) {
            Schema::create('budget_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('budget_id');
                $table->uuid('account_id');

                $table->decimal('expected_amount', 15, 2);
                $table->string('notes', 500)->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('budget_id');
                $table->index('account_id');
                $table->unique(['budget_id', 'account_id'], 'budget_lines_unique');

                $table->foreign('budget_id')
                    ->references('id')->on('budgets')
                    ->cascadeOnDelete();
                $table->foreign('account_id')
                    ->references('id')->on('accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
