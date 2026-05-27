<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow Customer.email to be NULL.
 *
 * The Lead→Customer conversion at Quotation Win
 * (QuotationService::win) materializes a Customer from just the Lead title
 * when the rep doesn't have an email yet — common for early-stage B2B
 * prospects. The unique index stays in place; Postgres permits multiple
 * NULLs in a unique column by default, so two unfilled accounts don't
 * collide, and a later edit that supplies an email still enforces
 * uniqueness against the existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
