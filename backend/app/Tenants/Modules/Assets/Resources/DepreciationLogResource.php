<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepreciationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'assetId'                 => $this->asset_id,
            'periodDate'              => $this->period_date,
            'depreciationAmount'      => (float) $this->depreciation_amount,
            'accumulatedDepreciation' => (float) $this->accumulated_depreciation,
            'bookValue'               => (float) $this->book_value,
            'method'                  => $this->method,
            'journalEntryId'          => $this->journal_entry_id,
            'createdAt'               => $this->created_at,
        ];
    }
}
