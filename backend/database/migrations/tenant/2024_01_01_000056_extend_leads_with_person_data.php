<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend Lead with person/account capture fields so a new lead can be created
 * as a full prospect record rather than just a deal title.
 *
 *   first_name / last_name  — contact person on the lead
 *   email / phone           — direct contact channels
 *   customer_type           — target account type (individual|business|tenant)
 *   address                 — free-form mailing/site address
 *
 * `title` becomes nullable; `LeadService::createLead` auto-derives it from
 * "{first_name} {last_name}" when the caller doesn't supply one, so the
 * Opportunity Kanban (which reads lead.title) keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'first_name'))    $table->string('first_name', 100)->nullable()->after('title');
            if (!Schema::hasColumn('leads', 'last_name'))     $table->string('last_name', 100)->nullable()->after('first_name');
            if (!Schema::hasColumn('leads', 'email'))         $table->string('email')->nullable()->after('last_name');
            if (!Schema::hasColumn('leads', 'phone'))         $table->string('phone', 50)->nullable()->after('email');
            if (!Schema::hasColumn('leads', 'customer_type')) $table->string('customer_type', 20)->default('business')->after('phone');
            if (!Schema::hasColumn('leads', 'address'))       $table->text('address')->nullable()->after('customer_type');

            // Index on email for dedup lookups + the leads.vue search box.
            $table->index(['tenant_id', 'email'], 'leads_email_idx');
        });

        // title becomes nullable — auto-derived in LeadService::createLead.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_email_idx');
            foreach (['first_name', 'last_name', 'email', 'phone', 'customer_type', 'address'] as $c) {
                if (Schema::hasColumn('leads', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
