<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Events;

use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an Opportunity reaches stage=won. Replaces the legacy
 * OpportunityWon + CreateDraftQuotationOnOpportunityWon auto-creation. Sales
 * listens via HandleLeadQualified and surfaces a "Create Quotation from
 * Lead" task — no Quotation is auto-created.
 *
 * `opportunity` arrives with `productSchedule` eager-loaded so the listener
 * can snapshot the line items without re-querying.
 */
class LeadQualified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?Lead $lead,
        public readonly Opportunity $opportunity,
    ) {}
}
