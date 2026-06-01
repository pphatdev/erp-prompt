<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashAdvanceSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'settlementNumber'       => $this->settlement_number,

            'cashAdvanceId'          => $this->cash_advance_id,
            'cashAdvance'            => $this->whenLoaded('cashAdvance', fn () => [
                'id'                  => $this->cashAdvance?->id,
                'advanceNumber'       => $this->cashAdvance?->advance_number,
                'employee'            => $this->cashAdvance?->employee ? [
                    'id'         => $this->cashAdvance->employee->id,
                    'employeeId' => $this->cashAdvance->employee->employee_id,
                    'fullName'   => trim(($this->cashAdvance->employee->first_name ?? '') . ' ' . ($this->cashAdvance->employee->last_name ?? '')) ?: null,
                ] : null,
                'receivableAccount'   => $this->cashAdvance?->receivableAccount ? [
                    'id'   => $this->cashAdvance->receivableAccount->id,
                    'code' => $this->cashAdvance->receivableAccount->code,
                    'name' => $this->cashAdvance->receivableAccount->name,
                ] : null,
                'amount'              => $this->cashAdvance ? (float) $this->cashAdvance->amount : null,
                'settledAmount'       => $this->cashAdvance ? (float) $this->cashAdvance->settled_amount : null,
                'outstandingAmount'   => $this->cashAdvance?->outstandingAmount(),
                'status'              => $this->cashAdvance?->status,
                'currency'            => $this->cashAdvance?->currency,
            ]),

            'bankAccountId'          => $this->bank_account_id,
            'bankAccount'            => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id'       => $this->bankAccount->id,
                'name'     => $this->bankAccount->name,
                'bankName' => $this->bankAccount->bank_name,
                'currency' => $this->bankAccount->currency,
            ] : null),

            'settledOn'              => optional($this->settled_on)->toDateString(),
            'actualAmount'           => (float) $this->actual_amount,
            'unusedReturned'         => (float) $this->unused_returned,
            'appliedToAdvance'       => $this->appliedToAdvance(),

            'paymentMethod'          => $this->payment_method,
            'referenceNumber'        => $this->reference_number,

            'status'                 => $this->status,
            'isCancellable'          => $this->isCancellable(),

            'journalEntryId'         => $this->journal_entry_id,
            'reversalJournalEntryId' => $this->reversal_journal_entry_id,

            'notes'                  => $this->notes,
            'lines'                  => CashAdvanceSettlementLineResource::collection($this->whenLoaded('lines')),

            'createdAt'              => optional($this->created_at)->toIso8601String(),
            'updatedAt'              => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
