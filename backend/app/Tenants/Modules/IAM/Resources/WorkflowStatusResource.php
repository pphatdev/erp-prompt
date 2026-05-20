<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'key' => $this->key,
            'label' => $this->label,
            'color' => $this->color,
            'icon' => $this->icon,
            'sequence' => (int) $this->sequence,
            'isInitial' => (bool) $this->is_initial,
            'isTerminal' => (bool) $this->is_terminal,
            'allowedNext' => $this->allowed_next ?? [],
            'metadata' => $this->metadata,
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
