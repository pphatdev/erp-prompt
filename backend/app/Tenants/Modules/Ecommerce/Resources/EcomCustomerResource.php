<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone,
            'isGuest' => (bool) $this->is_guest,
            'isActive' => (bool) $this->is_active,
            'emailVerifiedAt' => optional($this->email_verified_at)->toIso8601String(),
            'lastLoginAt' => optional($this->last_login_at)->toIso8601String(),
            'addresses' => EcomAddressResource::collection($this->whenLoaded('addresses')),
            'orderCount' => $this->whenLoaded('orders', fn () => $this->orders->count()),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
