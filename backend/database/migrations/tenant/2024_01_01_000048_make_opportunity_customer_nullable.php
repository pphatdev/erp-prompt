<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow Opportunity to exist without a Customer.
 *
 * Customer creation moves from CRM (LeadService::qualifyToOpportunity) to
 * Sales (QuotationService::win). Until a Quotation is marked Won, the
 * Opportunity may have no associated Customer yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable(false)->change();
        });
    }
};
