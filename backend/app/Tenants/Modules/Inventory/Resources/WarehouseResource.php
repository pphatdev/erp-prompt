<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'location'    => $this->location,
            'managerId'   => $this->manager_id,
            'manager'     => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id'   => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'addressLine' => $this->address_line,
            'city'        => $this->city,
            'country'     => $this->country,
            'capacity'    => $this->capacity !== null ? (int) $this->capacity : null,
            'isActive'    => (bool) $this->is_active,
            'notes'       => $this->notes,
            'createdAt'   => $this->created_at?->toIso8601String(),
            'updatedAt'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
