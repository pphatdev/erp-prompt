<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'advanceNumber'          => $this->advance_number,

            'employeeId'             => $this->employee_id,
            'employee'               => $this->whenLoaded('employee', fn () => [
                'id'         => $this->employee?->id,
                'employeeId' => $this->employee?->employee_id,
                'fullName'   => trim(($this->employee?->first_name ?? '') . ' ' . ($this->employee?->last_name ?? '')) ?: null,
            ]),

            'bankAccountId'          => $this->bank_account_id,
            'bankAccount'            => $this->whenLoaded('bankAccount', fn () => [
                'id'       => $this->bankAccount?->id,
                'name'     => $this->bankAccount?->name,
                'bankName' => $this->bankAccount?->bank_name,
                'currency' => $this->bankAccount?->currency,
            ]),

            'receivableAccountId'    => $this->receivable_account_id,
            'receivableAccount'      => $this->whenLoaded('receivableAccount', fn () => [
                'id'   => $this->receivableAccount?->id,
                'code' => $this->receivableAccount?->code,
                'name' => $this->receivableAccount?->name,
                'type' => $this->receivableAccount?->type,
            ]),

            'issuedOn'               => optional($this->issued_on)->toDateString(),
            'amount'                 => (float) $this->amount,
            'settledAmount'          => (float) $this->settled_amount,
            'outstandingAmount'      => $this->outstandingAmount(),
            'currency'               => $this->currency,

            'paymentMethod'          => $this->payment_method,
            'referenceNumber'        => $this->reference_number,
            'purpose'                => $this->purpose,

            'status'                 => $this->status,
            'isCancellable'          => $this->isCancellable(),

            'journalEntryId'         => $this->journal_entry_id,
            'reversalJournalEntryId' => $this->reversal_journal_entry_id,

            'notes'                  => $this->notes,

            'createdAt'              => optional($this->created_at)->toIso8601String(),
            'updatedAt'              => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
