<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'reference_number'       => $this->reference_number,
            'description'            => $this->description,
            'entry_date'             => optional($this->entry_date)->toDateString(),
            'status'                 => $this->status,
            'reverses_journal_id'    => $this->reverses_journal_id,
            'reversed_by_journal_id' => $this->reversed_by_journal_id,
            'lines'                  => LedgerEntryResource::collection($this->whenLoaded('entries')),
            'created_at'             => optional($this->created_at)->toISOString(),
        ];
    }
}
