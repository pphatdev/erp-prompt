<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Services;

use App\Models\Tenant\Opportunity;
use App\Models\Tenant\OpportunityProductSchedule;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use DomainException;
use Illuminate\Support\Collection;

class OpportunityProductScheduleService
{
    public function listFor(Opportunity $opp): Collection
    {
        return $opp->productSchedule()->with(['product', 'variant'])->orderBy('created_at')->get();
    }

    public function addLine(Opportunity $opp, array $data): OpportunityProductSchedule
    {
        $this->assertEditable($opp);

        $product = Product::findOrFail($data['product_id']);
        $variant = null;
        $unitPrice = (float) ($data['estimated_unit_price'] ?? $product->unit_price);

        if (!empty($data['variant_id'])) {
            $variant = ProductVariant::where('product_id', $product->id)->findOrFail($data['variant_id']);
            if (!isset($data['estimated_unit_price'])) {
                $unitPrice = (float) $variant->unit_price;
            }
        }

        return OpportunityProductSchedule::create([
            'opportunity_id'       => $opp->id,
            'product_id'           => $product->id,
            'variant_id'           => $variant?->id,
            'quantity'             => (float) ($data['quantity'] ?? 1),
            'estimated_unit_price' => $unitPrice,
            'cadence'              => $data['cadence'] ?? OpportunityProductSchedule::CADENCE_ONE_TIME,
            'notes'                => $data['notes'] ?? null,
        ]);
    }

    public function updateLine(OpportunityProductSchedule $line, array $data): OpportunityProductSchedule
    {
        $this->assertEditable($line->opportunity);

        if (!empty($data['variant_id'])) {
            ProductVariant::where('product_id', $line->product_id)->findOrFail($data['variant_id']);
        }

        $line->update(array_filter([
            'variant_id'           => $data['variant_id'] ?? $line->variant_id,
            'quantity'             => $data['quantity'] ?? null,
            'estimated_unit_price' => $data['estimated_unit_price'] ?? null,
            'cadence'              => $data['cadence'] ?? null,
            'notes'                => array_key_exists('notes', $data) ? $data['notes'] : null,
        ], fn ($v) => $v !== null));

        return $line->fresh(['product', 'variant']);
    }

    public function removeLine(OpportunityProductSchedule $line): void
    {
        $this->assertEditable($line->opportunity);
        $line->delete();
    }

    /**
     * Build a Quotation-ready items array from the schedule. Called by the
     * Sales-side "Create Quotation from Lead" flow.
     */
    public function snapshotToQuotationItems(Opportunity $opp): array
    {
        return $this->listFor($opp)->map(fn (OpportunityProductSchedule $line) => [
            'product_id' => $line->product_id,
            'variant_id' => $line->variant_id,
            'quantity'   => (float) $line->quantity,
            'unit_price' => (float) $line->estimated_unit_price,
            'notes'      => $line->notes,
        ])->all();
    }

    private function assertEditable(Opportunity $opp): void
    {
        if ($opp->isTerminal()) {
            throw new DomainException(
                "Opportunity is {$opp->stage}; product schedule is read-only on terminal stages."
            );
        }
    }
}
