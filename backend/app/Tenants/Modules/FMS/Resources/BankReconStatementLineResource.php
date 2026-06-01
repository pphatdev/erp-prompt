<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankReconStatementLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'sessionId'             => $this->session_id,
            'statementDate'         => optional($this->statement_date)->toDateString(),
            'description'           => $this->description,
            'referenceNumber'       => $this->reference_number,
            'amount'                => (float) $this->amount,
            'direction'             => $this->isDeposit() ? 'deposit' : ($this->isWithdrawal() ? 'withdrawal' : 'zero'),
            'matchedLedgerEntryId'  => $this->matched_ledger_entry_id,
            'matchedLedgerEntry'    => $this->whenLoaded('matchedLedgerEntry', fn () => $this->matchedLedgerEntry ? [
                'id'             => $this->matchedLedgerEntry->id,
                'journalEntryId' => $this->matchedLedgerEntry->journal_entry_id,
                'debit'          => (float) $this->matchedLedgerEntry->debit,
                'credit'         => (float) $this->matchedLedgerEntry->credit,
            ] : null),
            'isMatched'             => $this->isMatched(),
            'notes'                 => $this->notes,
            'createdAt'             => optional($this->created_at)->toIso8601String(),
        ];
    }
}
