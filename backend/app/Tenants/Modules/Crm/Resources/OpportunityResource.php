<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'leadId'             => $this->lead_id,
            'customerId'         => $this->customer_id,
            'title'              => $this->title,
            'stage'              => $this->stage,
            'estimatedValue'     => (float) $this->estimated_value,
            'probability'        => (int) $this->probability,
            'projectedCloseDate' => $this->close_date?->toDateString(),
            'lossReason'         => $this->loss_reason,
            'notes'              => $this->notes,
            'customer'           => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'lead'               => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id'    => $this->lead->id,
                'title' => $this->lead->title,
            ] : null),
            'createdAt'          => $this->created_at?->toIso8601String(),
            'updatedAt'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
