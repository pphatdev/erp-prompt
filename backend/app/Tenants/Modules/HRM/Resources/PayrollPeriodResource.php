<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'status' => $this->status,
            'payslipCount' => $this->whenCounted('payslips'),
            'journalEntryId' => $this->journal_entry_id,
            'closedAt' => optional($this->closed_at)->toIso8601String(),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
