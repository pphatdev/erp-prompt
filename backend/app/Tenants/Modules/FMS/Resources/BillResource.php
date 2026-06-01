<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'billNumber'            => $this->bill_number,
            'supplierInvoiceNumber' => $this->supplier_invoice_number,

            'supplierId'            => $this->supplier_id,
            'supplier'              => $this->whenLoaded('supplier', fn () => [
                'id'   => $this->supplier?->id,
                'code' => $this->supplier?->code,
                'name' => $this->supplier?->name,
            ]),
            'poId'                  => $this->po_id,

            'issueDate'             => optional($this->issue_date)->toDateString(),
            'dueDate'               => optional($this->due_date)->toDateString(),
            'currency'              => $this->currency,

            'subtotal'              => (float) $this->subtotal,
            'taxAmount'             => (float) $this->tax_amount,
            'total'                 => (float) $this->total,
            'paidAmount'            => (float) $this->paid_amount,
            'outstandingAmount'     => $this->outstandingAmount(),

            'status'                => $this->status,
            'isEditable'            => $this->isEditable(),
            'isPostable'            => $this->isPostable(),
            'isReversible'          => $this->isReversible(),

            'payableAccountId'      => $this->payable_account_id,
            'payableAccount'        => $this->whenLoaded('payableAccount', fn () => [
                'id'   => $this->payableAccount?->id,
                'code' => $this->payableAccount?->code,
                'name' => $this->payableAccount?->name,
                'type' => $this->payableAccount?->type,
            ]),
            'journalEntryId'         => $this->journal_entry_id,
            'reversalJournalEntryId' => $this->reversal_journal_entry_id,

            'notes'                 => $this->notes,
            'lines'                 => BillLineResource::collection($this->whenLoaded('lines')),

            'createdAt'             => optional($this->created_at)->toIso8601String(),
            'updatedAt'             => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
