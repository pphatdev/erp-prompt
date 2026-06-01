<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'receiptNumber'          => $this->receipt_number,

            'customerId'             => $this->customer_id,
            'customer'               => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer?->id,
                'name' => $this->customer?->name,
            ]),

            'bankAccountId'          => $this->bank_account_id,
            'bankAccount'            => $this->whenLoaded('bankAccount', fn () => [
                'id'       => $this->bankAccount?->id,
                'name'     => $this->bankAccount?->name,
                'bankName' => $this->bankAccount?->bank_name,
                'currency' => $this->bankAccount?->currency,
            ]),

            'arAccountId'            => $this->ar_account_id,
            'arAccount'              => $this->whenLoaded('arAccount', fn () => [
                'id'   => $this->arAccount?->id,
                'code' => $this->arAccount?->code,
                'name' => $this->arAccount?->name,
                'type' => $this->arAccount?->type,
            ]),

            'receivedOn'             => optional($this->received_on)->toDateString(),
            'amount'                 => (float) $this->amount,
            'currency'               => $this->currency,

            'paymentMethod'          => $this->payment_method,
            'referenceNumber'        => $this->reference_number,

            'status'                 => $this->status,
            'isCancellable'          => $this->isCancellable(),

            'journalEntryId'         => $this->journal_entry_id,
            'reversalJournalEntryId' => $this->reversal_journal_entry_id,

            'notes'                  => $this->notes,
            'applications'           => ReceiptInvoiceApplicationResource::collection($this->whenLoaded('applications')),

            'createdAt'              => optional($this->created_at)->toIso8601String(),
            'updatedAt'              => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
