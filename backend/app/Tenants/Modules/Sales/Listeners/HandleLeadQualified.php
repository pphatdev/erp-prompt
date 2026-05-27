<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Listeners;

use App\Models\Tenant\CrmActivity;
use App\Tenants\Modules\Crm\Events\LeadQualified;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the legacy CreateDraftQuotationOnOpportunityWon listener.
 *
 * Instead of silently auto-creating an empty Quotation, this surfaces a
 * follow-up task on the Opportunity for the assigned rep. The rep clicks the
 * task → opens the Quotation builder pre-filled from the Opportunity's B2B
 * Product Schedule snapshot.
 *
 * The "task" is persisted as a CrmActivity of type `task` attached
 * polymorphically to the Opportunity — surfaces naturally in the existing
 * activity timeline UI.
 */
class HandleLeadQualified
{
    public function handle(LeadQualified $event): void
    {
        $opp = $event->opportunity;
        $lead = $event->lead;

        $subject = $lead
            ? "Create Quotation from Lead: {$lead->title}"
            : "Create Quotation from Opportunity: {$opp->title}";

        CrmActivity::create([
            'trackable_type' => \App\Models\Tenant\Opportunity::class,
            'trackable_id'   => $opp->id,
            'activity_type'  => 'task',
            'subject'        => $subject,
            'description'    => 'Lead is qualified. Build a Quotation from the B2B Product Schedule.',
            'due_date'       => now()->addDays(3),
            'status'         => 'pending',
        ]);

        Log::info('HandleLeadQualified: created sales task.', [
            'opportunity_id' => $opp->id,
            'lead_id'        => $lead?->id,
        ]);
    }
}
