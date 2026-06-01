<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'periodNumber'                => $this->period_number,
            'name'                        => $this->name,
            'startDate'                   => optional($this->start_date)->toDateString(),
            'endDate'                     => optional($this->end_date)->toDateString(),
            'status'                      => $this->status,
            'isClosable'                  => $this->isClosable(),
            'isReopenable'                => $this->isReopenable(),
            'lockedAt'                    => optional($this->locked_at)->toIso8601String(),
            'lockedBy'                    => $this->locked_by,
            'retainedEarningsAccountId'   => $this->retained_earnings_account_id,
            'retainedEarningsAccount'     => $this->whenLoaded('retainedEarningsAccount', fn () => $this->retainedEarningsAccount ? [
                'id'   => $this->retainedEarningsAccount->id,
                'code' => $this->retainedEarningsAccount->code,
                'name' => $this->retainedEarningsAccount->name,
                'type' => $this->retainedEarningsAccount->type,
            ] : null),
            'closingJournalEntryId'       => $this->closing_journal_entry_id,
            'closingJournalEntry'         => $this->whenLoaded('closingJournalEntry', fn () => $this->closingJournalEntry ? [
                'id'              => $this->closingJournalEntry->id,
                'referenceNumber' => $this->closingJournalEntry->reference_number,
                'description'     => $this->closingJournalEntry->description,
                'entryDate'       => optional($this->closingJournalEntry->entry_date)->toDateString(),
                'status'          => $this->closingJournalEntry->status,
            ] : null),
            'notes'                       => $this->notes,
            'createdAt'                   => optional($this->created_at)->toIso8601String(),
            'updatedAt'                   => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
