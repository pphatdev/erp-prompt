<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityProductScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'opportunityId'       => $this->opportunity_id,
            'productId'           => $this->product_id,
            'variantId'           => $this->variant_id,
            'productName'         => $this->whenLoaded('product', fn () => $this->product->name),
            'variantSku'          => $this->whenLoaded('variant', fn () => $this->variant?->sku),
            'quantity'            => (float) $this->quantity,
            'estimatedUnitPrice'  => (float) $this->estimated_unit_price,
            'cadence'             => $this->cadence,
            'notes'               => $this->notes,
            'createdAt'           => optional($this->created_at)->toIso8601String(),
            'updatedAt'           => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
