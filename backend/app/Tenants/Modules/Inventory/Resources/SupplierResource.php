<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'contactName'   => $this->contact_name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'address'       => $this->address,
            'website'       => $this->website,
            'taxId'         => $this->tax_id,
            'paymentTerms'  => $this->payment_terms,
            'leadTimeDays'  => $this->lead_time_days,
            'rating'        => $this->rating,
            'isActive'      => (bool) $this->is_active,
            'notes'         => $this->notes,
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
