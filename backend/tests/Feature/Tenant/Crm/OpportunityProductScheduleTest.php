<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Tenant\Opportunity;
use App\Models\Tenant\OpportunityProductSchedule;
use App\Models\Tenant\Product;
use App\Tenants\Modules\Crm\Services\OpportunityProductScheduleService;
use Tests\Feature\TenantTestCase;

class OpportunityProductScheduleTest extends TenantTestCase
{
    private OpportunityProductScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OpportunityProductScheduleService::class);
    }

    public function test_add_line_creates_schedule_row_with_defaults(): void
    {
        $opp = $this->seedOpportunity(stage: Opportunity::STAGE_PROPOSAL);
        $product = $this->seedProduct(unitPrice: 99.0);

        $line = $this->service->addLine($opp, [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        $this->assertSame($product->id, $line->product_id);
        $this->assertSame(99.0, (float) $line->estimated_unit_price);
        $this->assertSame(3.0, (float) $line->quantity);
        $this->assertSame(OpportunityProductSchedule::CADENCE_ONE_TIME, $line->cadence);
    }

    public function test_add_line_blocked_when_opportunity_is_won(): void
    {
        $opp = $this->seedOpportunity(stage: Opportunity::STAGE_WON);
        $product = $this->seedProduct();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('read-only on terminal stages');
        $this->service->addLine($opp, ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_update_line_blocked_when_opportunity_is_lost(): void
    {
        $opp = $this->seedOpportunity(stage: Opportunity::STAGE_PROPOSAL);
        $product = $this->seedProduct();
        $line = $this->service->addLine($opp, ['product_id' => $product->id, 'quantity' => 1]);

        $opp->update(['stage' => Opportunity::STAGE_LOST, 'loss_reason' => 'No budget']);

        $this->expectException(\DomainException::class);
        $this->service->updateLine($line->fresh(), ['quantity' => 5]);
    }

    public function test_snapshot_returns_quotation_ready_items(): void
    {
        $opp = $this->seedOpportunity(stage: Opportunity::STAGE_PROPOSAL);
        $p1 = $this->seedProduct(sku: 'A', unitPrice: 10);
        $p2 = $this->seedProduct(sku: 'B', unitPrice: 20);
        $this->service->addLine($opp, ['product_id' => $p1->id, 'quantity' => 2, 'estimated_unit_price' => 12]);
        $this->service->addLine($opp, ['product_id' => $p2->id, 'quantity' => 1]);

        $items = $this->service->snapshotToQuotationItems($opp->fresh());

        $this->assertCount(2, $items);
        $this->assertSame($p1->id, $items[0]['product_id']);
        $this->assertSame(12.0, $items[0]['unit_price']);
        $this->assertSame($p2->id, $items[1]['product_id']);
        $this->assertSame(20.0, $items[1]['unit_price']);
    }

    private function seedOpportunity(string $stage): Opportunity
    {
        return Opportunity::create([
            'title' => 'Prospect deal',
            'stage' => $stage,
            'loss_reason' => $stage === Opportunity::STAGE_LOST ? 'seeded' : null,
        ]);
    }

    private function seedProduct(string $sku = 'SW-X', float $unitPrice = 100.0): Product
    {
        return Product::create([
            'sku' => $sku . '-' . uniqid(),
            'name' => 'Test Product',
            'product_type' => Product::TYPE_SOFTWARE,
            'unit_price' => $unitPrice,
            'minimum_stock_level' => 0,
        ]);
    }
}
