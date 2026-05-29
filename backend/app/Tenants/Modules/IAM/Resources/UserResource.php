<?php

namespace App\Tenants\Modules\IAM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            // Linked employee — `whenLoaded` so we don't N+1 in places that
            // never use it. Callers needing the code (TT-0001) eager-load
            // `requester.employee` / `approver.employee` on their queries.
            'employee' => $this->whenLoaded('employee', fn () => $this->employee
                ? new \App\Tenants\Modules\HRM\Resources\EmployeeResource($this->employee)
                : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
