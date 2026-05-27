<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow Quotation to exist without a Customer.
 *
 * In the target hybrid sales flow, a Quotation can be created from a
 * qualified Opportunity whose Lead has no Customer yet — the Customer is
 * materialized at Quotation Win (QuotationService::win). Until then the
 * customer_id stays null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable(false)->change();
        });
    }
};
