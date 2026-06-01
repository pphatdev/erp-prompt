<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptInvoiceApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->whenLoaded('invoice', fn () => $this->invoice);
        $invoiceTotal     = $this->invoice ? (float) $this->invoice->total_amount : null;
        $invoicePaid      = $this->invoice ? (float) $this->invoice->paid_amount : null;
        $invoiceOutstanding = ($invoiceTotal !== null && $invoicePaid !== null)
            ? round($invoiceTotal - $invoicePaid, 2)
            : null;

        return [
            'id'                  => $this->id,
            'invoiceId'           => $this->invoice_id,
            'invoice'             => $this->invoice ? [
                'id'                => $this->invoice->id,
                'invoiceNumber'     => $this->invoice->invoice_number,
                'status'            => $this->invoice->status,
                'invoiceDate'       => optional($this->invoice->invoice_date)->toDateString(),
                'dueDate'           => optional($this->invoice->due_date)->toDateString(),
                'totalAmount'       => $invoiceTotal,
                'paidAmount'        => $invoicePaid,
                'outstandingAmount' => $invoiceOutstanding,
            ] : null,
            'appliedAmount'       => (float) $this->applied_amount,
        ];
    }
}
