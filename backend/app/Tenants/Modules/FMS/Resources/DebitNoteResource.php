<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebitNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'debitNoteNumber'        => $this->debit_note_number,

            'customerId'             => $this->customer_id,
            'customer'               => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer?->id,
                'name' => $this->customer?->name,
            ]),

            'invoiceId'              => $this->invoice_id,
            'invoice'                => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id'                => $this->invoice->id,
                'invoiceNumber'     => $this->invoice->invoice_number,
                'status'            => $this->invoice->status,
                'invoiceDate'       => optional($this->invoice->invoice_date)->toDateString(),
                'dueDate'           => optional($this->invoice->due_date)->toDateString(),
                'totalAmount'       => (float) $this->invoice->total_amount,
                'paidAmount'        => (float) $this->invoice->paid_amount,
                'outstandingAmount' => round((float) $this->invoice->total_amount - (float) $this->invoice->paid_amount, 2),
            ] : null),

            'revenueAccountId'       => $this->revenue_account_id,
            'revenueAccount'         => $this->whenLoaded('revenueAccount', fn () => [
                'id'   => $this->revenueAccount?->id,
                'code' => $this->revenueAccount?->code,
                'name' => $this->revenueAccount?->name,
                'type' => $this->revenueAccount?->type,
            ]),

            'arAccountId'            => $this->ar_account_id,
            'arAccount'              => $this->whenLoaded('arAccount', fn () => [
                'id'   => $this->arAccount?->id,
                'code' => $this->arAccount?->code,
                'name' => $this->arAccount?->name,
                'type' => $this->arAccount?->type,
            ]),

            'issueDate'              => optional($this->issue_date)->toDateString(),
            'amount'                 => (float) $this->amount,
            'currency'               => $this->currency,

            'reason'                 => $this->reason,

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
