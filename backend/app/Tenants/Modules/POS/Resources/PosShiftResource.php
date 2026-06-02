<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terminalId' => $this->terminal_id,
            'terminal' => new PosTerminalResource($this->whenLoaded('terminal')),
            'cashierId' => $this->cashier_id,
            'cashierName' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'openedAt' => optional($this->opened_at)->toIso8601String(),
            'closedAt' => optional($this->closed_at)->toIso8601String(),
            'openingFloat' => (float) $this->opening_float,
            'expectedCash' => $this->expected_cash !== null ? (float) $this->expected_cash : null,
            'closingCash' => $this->closing_cash !== null ? (float) $this->closing_cash : null,
            'variance' => $this->variance !== null ? (float) $this->variance : null,
            'status' => $this->status,
            'reconciledBy' => $this->reconciled_by,
            'reconciledAt' => optional($this->reconciled_at)->toIso8601String(),
            'varianceJournalEntryId' => $this->variance_journal_entry_id,
            'notes' => $this->notes,
            'orderCount' => $this->whenLoaded('orders', fn () => $this->orders->count()),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
