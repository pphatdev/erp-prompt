<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Identity
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'companyName' => $this->company_name,
            'status' => $this->status,

            // Classification
            'customerType' => $this->customer_type,
            'externalCode' => $this->external_code,
            'tier' => $this->tier,

            // Business identifiers
            'taxId' => $this->tax_id,
            'industry' => $this->industry,
            'website' => $this->website,

            // Address — both free-form and structured surfaces, frontend
            // displays whichever it has.
            'address' => $this->address,
            'billingCity' => $this->billing_city,
            'billingState' => $this->billing_state,
            'billingPostalCode' => $this->billing_postal_code,
            'billingCountry' => $this->billing_country,

            // Locale
            'currency' => $this->currency,
            'language' => $this->language,
            'timezone' => $this->timezone,

            // Account ownership
            'accountManagerId' => $this->account_manager_id,
            'accountManager' => $this->whenLoaded('accountManager', fn () => [
                'id' => $this->accountManager?->id,
                'name' => $this->accountManager?->name,
                'email' => $this->accountManager?->email,
            ]),

            // Notes
            'notes' => $this->notes,

            // Branding
            'brandPrimaryColor' => $this->brand_primary_color,
            'brandLogoUrl' => $this->brand_logo_url,

            // Tenant linkage
            'tenantHandle'        => $this->tenant_handle,
            'provisionedTenantId' => $this->provisioned_tenant_id,
            'provisionedAt'       => optional($this->provisioned_at)->toIso8601String(),
            // Full subdomain URL assembled from handle + platform.system_domain.
            // Null until the subscription is confirmed and provisioning completes.
            'provisionedSubdomain' => ($this->tenant_handle && $this->provisioned_tenant_id)
                ? $this->tenant_handle . '.' . config('platform.system_domain', 'localhost')
                : null,

            // Timestamps
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),

            // Detail-page relations
            'orders' => $this->whenLoaded('orders', fn () => $this->orders->map(fn ($o) => [
                'id' => $o->id,
                'orderNumber' => $o->order_number,
                'status' => $o->status,
                'totalAmount' => (float) $o->total_amount,
                'createdAt' => optional($o->created_at)->toIso8601String(),
            ])),
            'leads' => $this->whenLoaded('leads', fn () => $this->leads->map(fn ($l) => [
                'id' => $l->id,
                'title' => $l->title,
                'status' => $l->status,
                'estimatedValue' => (float) ($l->estimated_value ?? 0),
            ])),
        ];
    }
}
