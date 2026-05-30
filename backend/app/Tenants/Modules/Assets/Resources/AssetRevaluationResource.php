<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRevaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'assetId'           => $this->asset_id,
            'appraisalDate'     => $this->appraisal_date,
            'previousValue'     => (float) $this->previous_value,
            'appraisalValue'    => (float) $this->appraisal_value,
            'adjustmentAmount'  => (float) $this->adjustment_amount,
            'adjustmentType'    => $this->adjustment_type,
            'appraiser'         => $this->appraiser,
            'notes'             => $this->notes,
            'journalEntryId'    => $this->journal_entry_id,
            'createdAt'         => $this->created_at,
        ];
    }
}
