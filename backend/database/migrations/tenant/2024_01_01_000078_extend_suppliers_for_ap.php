<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP / Vendor extension on the existing `suppliers` table. Vendor is the same
 * real-world entity as Supplier — these fields flip a procurement supplier
 * into an AP-payable vendor (bank details, default GL accounts, is_vendor flag).
 *
 * See: skills/accounting/rules.md, hybrid_sales_business_flow.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'is_vendor')) {
                $table->boolean('is_vendor')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('suppliers', 'payment_method')) {
                // Free-string lookup matched by frontend dropdown — e.g.
                // 'bank_transfer', 'cheque', 'cash', 'wire'. Kept open so
                // tenants can add their own methods without a migration.
                $table->string('payment_method', 40)->nullable()->after('is_vendor');
            }
            if (!Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name', 160)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('suppliers', 'bank_account_name')) {
                $table->string('bank_account_name', 160)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('suppliers', 'bank_account_number')) {
                $table->string('bank_account_number', 60)->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('suppliers', 'bank_swift')) {
                $table->string('bank_swift', 20)->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('suppliers', 'default_payable_account_id')) {
                $table->uuid('default_payable_account_id')->nullable()->after('bank_swift');
            }
            if (!Schema::hasColumn('suppliers', 'default_expense_account_id')) {
                $table->uuid('default_expense_account_id')->nullable()->after('default_payable_account_id');
            }
        });

        // FKs in a separate pass keeps the migration safe when re-run on a DB
        // that already has the columns but missing constraints.
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreign('default_payable_account_id')
                ->references('id')->on('accounts')
                ->nullOnDelete();
            $table->foreign('default_expense_account_id')
                ->references('id')->on('accounts')
                ->nullOnDelete();

            $table->index('is_vendor');
            $table->index('default_payable_account_id');
            $table->index('default_expense_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['default_payable_account_id']);
            $table->dropForeign(['default_expense_account_id']);
            $table->dropIndex(['is_vendor']);
            $table->dropIndex(['default_payable_account_id']);
            $table->dropIndex(['default_expense_account_id']);

            $cols = [
                'is_vendor', 'payment_method',
                'bank_name', 'bank_account_name', 'bank_account_number', 'bank_swift',
                'default_payable_account_id', 'default_expense_account_id',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('suppliers', $c)) $table->dropColumn($c);
            }
        });
    }
};
