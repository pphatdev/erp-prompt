<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Approvals\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $this->module,
            'type' => $this->type,
            'levels' => $this->whenLoaded('levels', fn () => $this->levels->map(fn ($level) => [
                'id' => $level->id,
                'sequence' => (int) $level->sequence,
                'approverRole' => $level->approver_role,
                'approverId' => $level->approver_id,
            ])->all()),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
