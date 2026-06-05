<?php

namespace App\Tenants\Modules\IAM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'usersCount' => $this->when($this->users_count !== null, fn () => (int) $this->users_count),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
