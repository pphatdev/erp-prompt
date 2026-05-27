<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customerId'     => $this->customer_id,
            'title'          => $this->title,
            'firstName'      => $this->first_name,
            'lastName'       => $this->last_name,
            'fullName'       => $this->full_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'customerType'   => $this->customer_type,
            'address'        => $this->address,
            'status'         => $this->status,
            'source'         => $this->source,
            'estimatedValue' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'customer'       => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'createdAt'      => $this->created_at?->toIso8601String(),
            'updatedAt'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
