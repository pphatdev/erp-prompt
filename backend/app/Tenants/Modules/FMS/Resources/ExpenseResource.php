<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'expenseNumber'          => $this->expense_number,

            'bankAccountId'          => $this->bank_account_id,
            'bankAccount'            => $this->whenLoaded('bankAccount', fn () => [
                'id'       => $this->bankAccount?->id,
                'name'     => $this->bankAccount?->name,
                'bankName' => $this->bankAccount?->bank_name,
                'currency' => $this->bankAccount?->currency,
            ]),

            'supplierId'             => $this->supplier_id,
            'supplier'               => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),

            'paidOn'                 => optional($this->paid_on)->toDateString(),
            'total'                  => (float) $this->total,
            'currency'               => $this->currency,

            'paymentMethod'          => $this->payment_method,
            'referenceNumber'        => $this->reference_number,

            'status'                 => $this->status,
            'isCancellable'          => $this->isCancellable(),

            'journalEntryId'         => $this->journal_entry_id,
            'reversalJournalEntryId' => $this->reversal_journal_entry_id,

            'notes'                  => $this->notes,
            'lines'                  => ExpenseLineResource::collection($this->whenLoaded('lines')),

            'createdAt'              => optional($this->created_at)->toIso8601String(),
            'updatedAt'              => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
