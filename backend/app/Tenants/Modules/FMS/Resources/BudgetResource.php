<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $varianceMap = $this->additional['variance'] ?? null;
        $expectedTotal = $this->relationLoaded('lines')
            ? round((float) $this->lines->sum('expected_amount'), 2)
            : $this->expectedTotal();

        return [
            'id'              => $this->id,
            'budgetNumber'    => $this->budget_number,
            'name'            => $this->name,
            'startDate'       => optional($this->start_date)->toDateString(),
            'endDate'         => optional($this->end_date)->toDateString(),
            'status'          => $this->status,
            'isEditable'      => $this->isEditable(),
            'isActivatable'   => $this->isActivatable(),
            'isArchivable'    => $this->isArchivable(),
            'expectedTotal'   => $expectedTotal,
            'linesCount'      => $this->lines_count ?? ($this->relationLoaded('lines') ? $this->lines->count() : null),
            'notes'           => $this->notes,
            'lines'           => $this->whenLoaded('lines', fn () =>
                BudgetLineResource::collection($this->lines)
                    ->additional(['variance' => $varianceMap])),
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
