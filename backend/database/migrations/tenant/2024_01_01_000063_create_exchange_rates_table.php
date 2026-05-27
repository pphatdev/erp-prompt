<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('exchange_rates')) {
            return;
        }

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            // 18,6 — FX rates routinely carry 4-6 decimals; KHR ranges around 4,000-4,100/USD.
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->string('source', 32)->default('manual');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['base_currency', 'quote_currency', 'effective_date'], 'exchange_rates_pair_date_idx');
            $table->unique(
                ['tenant_id', 'base_currency', 'quote_currency', 'effective_date'],
                'exchange_rates_tenant_pair_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
