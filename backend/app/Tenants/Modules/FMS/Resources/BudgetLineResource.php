<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BudgetLineResource. When the parent collection passes a `variance` array
 * keyed by line id (BudgetVarianceResource does this), the per-line variance
 * snapshot is appended to the payload.
 */
class BudgetLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variance = null;
        $varianceMap = $this->additional['variance'] ?? null;
        if (is_array($varianceMap) && isset($varianceMap[$this->id])) {
            $variance = $varianceMap[$this->id];
        }

        return [
            'id'             => $this->id,
            'budgetId'       => $this->budget_id,
            'accountId'      => $this->account_id,
            'account'        => $this->whenLoaded('account', fn () => [
                'id'   => $this->account?->id,
                'code' => $this->account?->code,
                'name' => $this->account?->name,
                'type' => $this->account?->type,
            ]),
            'expectedAmount' => (float) $this->expected_amount,
            'notes'          => $this->notes,
            'variance'       => $variance,
        ];
    }
}
