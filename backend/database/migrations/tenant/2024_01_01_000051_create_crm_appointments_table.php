<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Appointments — calendar/timeline entity for prospect-facing meetings,
 * demos, follow-ups, and technical reviews. Separate from CrmActivity
 * (which captures already-completed interactions) — Appointments are
 * forward-looking and have explicit start/end times.
 *
 * Either `opportunity_id` or `lead_id` (or neither, for ad-hoc) may be set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location')->nullable();
            $table->jsonb('attendees')->nullable(); // array of {name, email, role?}
            $table->text('notes')->nullable();

            $table->uuid('opportunity_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('actor_id')->nullable(); // assigned rep

            $table->string('status', 20)->default('scheduled'); // scheduled|completed|cancelled|no_show
            $table->text('cancel_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'starts_at'], 'crm_appointments_window_idx');
            $table->index(['tenant_id', 'status'], 'crm_appointments_status_idx');
            $table->index('opportunity_id');
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_appointments');
    }
};
