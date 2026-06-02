<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcomPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'provider' => $this->provider,
            'providerChargeId' => $this->provider_charge_id,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'gatewayFee' => (float) $this->gateway_fee,
            'currency' => $this->currency,
            'clientUuid' => $this->client_uuid,
            'failureCode' => $this->failure_code,
            'failureMessage' => $this->failure_message,
            'capturedAt' => optional($this->captured_at)->toIso8601String(),
            'failedAt' => optional($this->failed_at)->toIso8601String(),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
