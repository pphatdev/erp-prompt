<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'subject'       => $this->subject,
            'startsAt'      => $this->starts_at?->toIso8601String(),
            'endsAt'        => $this->ends_at?->toIso8601String(),
            'location'      => $this->location,
            'attendees'     => $this->attendees ?? [],
            'notes'         => $this->notes,
            'status'        => $this->status,
            'opportunityId' => $this->opportunity_id,
            'leadId'        => $this->lead_id,
            'actorId'       => $this->actor_id,
            'opportunity'   => $this->whenLoaded('opportunity', fn () => $this->opportunity ? [
                'id'    => $this->opportunity->id,
                'title' => $this->opportunity->title,
            ] : null),
            'lead'          => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id'    => $this->lead->id,
                'title' => $this->lead->title,
            ] : null),
            'actor'         => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id'   => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'cancelReason'  => $this->cancel_reason,
            'completedAt'   => $this->completed_at?->toIso8601String(),
            'cancelledAt'   => $this->cancelled_at?->toIso8601String(),
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
