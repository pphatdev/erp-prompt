<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'customerId' => $this->customer_id,
            'fullName'   => $this->full_name,
            'firstName'  => $this->first_name,
            'lastName'   => $this->last_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'jobTitle'   => $this->job_title,
            'isPrimary'  => (bool) $this->is_primary,
            'customer'   => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'createdAt'  => $this->created_at?->toIso8601String(),
            'updatedAt'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
