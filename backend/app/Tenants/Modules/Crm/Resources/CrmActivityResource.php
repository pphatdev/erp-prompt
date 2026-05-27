<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'activityType'  => $this->activity_type,
            'subject'       => $this->subject,
            'description'   => $this->description,
            'dueDate'       => $this->due_date?->toIso8601String(),
            'status'        => $this->status,
            'trackableType' => $this->trackable_type,
            'trackableId'   => $this->trackable_id,
            'actorId'       => $this->actor_id,
            'actor'         => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id'   => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
