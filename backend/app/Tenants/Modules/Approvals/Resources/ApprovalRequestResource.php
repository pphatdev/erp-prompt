<?php

namespace App\Tenants\Modules\Approvals\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tenants\Modules\IAM\Resources\UserResource;

class ApprovalRequestResource extends JsonResource
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
            'workflow_id' => $this->workflow_id,
            'requester' => new UserResource($this->whenLoaded('requester')),
            'current_level_id' => $this->current_level_id,
            'requestable_type' => $this->requestable_type,
            'requestable_id' => $this->requestable_id,
            'status' => $this->status,
            'history' => $this->whenLoaded('history'), // Could create an ApprovalHistoryResource
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
