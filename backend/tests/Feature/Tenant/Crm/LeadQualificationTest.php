<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use App\Tenants\Modules\Crm\Services\LeadService;
use Tests\Feature\TenantTestCase;

/**
 * New behaviour: qualifyToOpportunity does NOT create Customer/CrmContact.
 * Customer creation is deferred to QuotationService::win — see
 * QuotationWinTest for that flow.
 */
class LeadQualificationTest extends TenantTestCase
{
    private LeadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeadService::class);
    }

    public function test_qualifying_lead_without_customer_creates_only_opportunity(): void
    {
        $lead = Lead::create([
            'title'           => 'Inbound Demo Request',
            'estimated_value' => 25000,
            'status'          => 'new',
            'source'          => 'web',
        ]);

        $customerCountBefore = Customer::count();
        $contactCountBefore  = CrmContact::count();
        $oppCountBefore      = Opportunity::count();

        $opp = $this->service->qualifyToOpportunity($lead, [
            'estimated_value' => 25000,
            'probability'     => 60,
        ]);

        $this->assertSame($customerCountBefore, Customer::count(), 'Customer must NOT be created at qualification.');
        $this->assertSame($contactCountBefore, CrmContact::count(), 'CrmContact must NOT be created at qualification.');
        $this->assertSame($oppCountBefore + 1, Opportunity::count());

        // Lead qualification creates the Opportunity in the initial Kanban
        // column ("Opportunities" = STAGE_NEW). The lead itself still flips
        // its own `status` to qualified — that's a Lead concept, distinct
        // from the Opportunity stage column it lands in.
        $this->assertSame(Opportunity::STAGE_NEW, $opp->stage);
        $this->assertNull($opp->customer_id, 'Opportunity may carry no Customer until Quotation::win.');
        $this->assertSame('qualified', $lead->fresh()->status);
    }

    public function test_qualifying_lead_with_existing_customer_links_it_to_opportunity(): void
    {
        $customer = Customer::create([
            'name'          => 'Existing Co',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);
        $lead = Lead::create([
            'title'       => 'Renewal',
            'customer_id' => $customer->id,
            'status'      => 'new',
        ]);

        $opp = $this->service->qualifyToOpportunity($lead, []);

        $this->assertSame($customer->id, $opp->customer_id);
        $this->assertSame(1, Customer::where('id', $customer->id)->count());
    }

    public function test_qualifying_passes_through_customer_id_from_payload_when_lead_has_none(): void
    {
        $existing = Customer::create([
            'name' => 'Provided Co', 'customer_type' => 'business', 'status' => 'active',
        ]);
        $lead = Lead::create(['title' => 'Provided lead', 'status' => 'new']);

        $opp = $this->service->qualifyToOpportunity($lead, ['customer_id' => $existing->id]);

        $this->assertSame($existing->id, $opp->customer_id);
        $this->assertSame($existing->id, $lead->fresh()->customer_id);
    }
}
