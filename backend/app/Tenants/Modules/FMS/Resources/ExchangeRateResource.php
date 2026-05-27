<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'baseCurrency'   => $this->base_currency,
            'quoteCurrency'  => $this->quote_currency,
            'pair'           => "{$this->base_currency}/{$this->quote_currency}",
            'rate'           => (float) $this->rate,
            'effectiveDate'  => optional($this->effective_date)->toDateString(),
            'source'         => $this->source,
            'notes'          => $this->notes,
            'isActive'       => (bool) $this->is_active,
            'createdAt'      => optional($this->created_at)->toISOString(),
            'updatedAt'      => optional($this->updated_at)->toISOString(),
        ];
    }
}
