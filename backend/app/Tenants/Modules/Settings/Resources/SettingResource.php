<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'group' => $this->group,
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'isPublic' => (bool) $this->is_public,
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
