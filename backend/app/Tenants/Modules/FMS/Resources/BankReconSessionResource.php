<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankReconSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $linesTotal = $this->relationLoaded('statementLines')
            ? round((float) $this->statementLines->sum('amount'), 2)
            : $this->statementLinesTotal();

        $unmatchedCount = $this->relationLoaded('statementLines')
            ? $this->statementLines->whereNull('matched_ledger_entry_id')->count()
            : $this->unmatchedLinesCount();

        return [
            'id'                       => $this->id,
            'sessionNumber'            => $this->session_number,

            'bankAccountId'            => $this->bank_account_id,
            'bankAccount'              => $this->whenLoaded('bankAccount', fn () => [
                'id'       => $this->bankAccount?->id,
                'name'     => $this->bankAccount?->name,
                'bankName' => $this->bankAccount?->bank_name,
                'currency' => $this->bankAccount?->currency,
                'glAccount'=> $this->bankAccount?->glAccount ? [
                    'id'   => $this->bankAccount->glAccount->id,
                    'code' => $this->bankAccount->glAccount->code,
                    'name' => $this->bankAccount->glAccount->name,
                    'balance' => (float) $this->bankAccount->glAccount->balance,
                ] : null,
            ]),

            'startDate'                => optional($this->start_date)->toDateString(),
            'endDate'                  => optional($this->end_date)->toDateString(),

            'openingBalance'           => (float) $this->opening_balance,
            'statementEndingBalance'   => (float) $this->statement_ending_balance,
            'bookEndingBalance'        => (float) $this->book_ending_balance,
            'statementLinesTotal'      => $linesTotal,
            'expectedEndingBalance'    => round((float) $this->opening_balance + $linesTotal, 2),
            'balanceMatches'           => $this->balanceMatches(),
            'unmatchedLinesCount'      => $unmatchedCount,

            'status'                   => $this->status,
            'isClosable'               => $this->isClosable(),

            'closedAt'                 => optional($this->closed_at)->toIso8601String(),
            'closedBy'                 => $this->closed_by,

            'notes'                    => $this->notes,
            'statementLines'           => BankReconStatementLineResource::collection($this->whenLoaded('statementLines')),

            'createdAt'                => optional($this->created_at)->toIso8601String(),
            'updatedAt'                => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
