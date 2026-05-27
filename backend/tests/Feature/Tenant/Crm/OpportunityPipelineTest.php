<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Opportunity;
use App\Tenants\Modules\Crm\Events\LeadQualified;
use App\Tenants\Modules\Crm\Services\OpportunityService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\TenantTestCase;

class OpportunityPipelineTest extends TenantTestCase
{
    private OpportunityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OpportunityService::class);
    }

    public function test_stage_transitions_through_pipeline(): void
    {
        $opp = $this->makeOpportunity();

        $advanced = $this->service->updateStage($opp, Opportunity::STAGE_PROPOSAL);
        $this->assertSame(Opportunity::STAGE_PROPOSAL, $advanced->stage);

        $negotiating = $this->service->updateStage($advanced, Opportunity::STAGE_NEGOTIATION);
        $this->assertSame(Opportunity::STAGE_NEGOTIATION, $negotiating->stage);
    }

    public function test_terminal_stage_cannot_be_moved(): void
    {
        Event::fake([LeadQualified::class]);

        $opp = $this->makeOpportunity();
        $won = $this->service->updateStage($opp, Opportunity::STAGE_WON);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already won');
        $this->service->updateStage($won, Opportunity::STAGE_PROPOSAL);
    }

    public function test_loss_reason_required_when_moving_to_lost(): void
    {
        $opp = $this->makeOpportunity();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('loss_reason is required');
        $this->service->updateStage($opp, Opportunity::STAGE_LOST);
    }

    public function test_loss_reason_persisted_when_marking_lost(): void
    {
        $opp = $this->makeOpportunity();
        $lost = $this->service->updateStage($opp, Opportunity::STAGE_LOST, 'Budget rejected');

        $this->assertSame(Opportunity::STAGE_LOST, $lost->stage);
        $this->assertSame('Budget rejected', $lost->loss_reason);
    }

    public function test_won_stage_dispatches_event(): void
    {
        Event::fake([LeadQualified::class]);

        $opp = $this->makeOpportunity();
        $this->service->updateStage($opp, Opportunity::STAGE_WON);

        Event::assertDispatched(LeadQualified::class, fn ($e) => $e->opportunity->id === $opp->id);
    }

    private function makeOpportunity(): Opportunity
    {
        $customer = Customer::create([
            'name'          => 'Acme Corp',
            'email'         => 'ops@acme.test',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);

        return Opportunity::create([
            'title'           => 'Big Deal',
            'customer_id'     => $customer->id,
            'stage'           => Opportunity::STAGE_QUALIFIED,
            'estimated_value' => 100000,
            'probability'     => 60,
        ]);
    }
}
