<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Chart of Accounts Table
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('type'); // asset, liability, equity, revenue, expense
                $table->uuid('parent_id')->nullable();
                $table->decimal('balance', 15, 2)->default(0);
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['code', 'tenant_id']);
            });

            Schema::table('accounts', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }

        // Journal Entries Table (The Header)
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference_number')->unique();
                $table->string('description')->nullable();
                $table->date('entry_date');
                $table->string('status')->default('draft'); // draft, posted, reversed
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index('tenant_id');
            });
        }

        // Ledger Entries Table (The Lines)
        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('journal_entry_id');
                $table->uuid('account_id');
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
