<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'code'      => $this->code,
            'name'      => $this->name,
            'type'      => $this->type,
            'parentId'  => $this->parent_id,
            'balance'   => (float) $this->balance,
            'createdAt' => optional($this->created_at)->toISOString(),
            'updatedAt' => optional($this->updated_at)->toISOString(),
            'children'  => AccountResource::collection($this->whenLoaded('children')),
        ];
    }
}
