<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'checklistId'        => $this->checklist_id,
            'title'              => $this->title,
            'description'        => $this->description,
            'ownerRole'          => $this->owner_role,
            'ownerUserId'        => $this->owner_user_id,
            'dueOffsetDays'      => (int) $this->due_offset_days,
            'dueDate'            => optional($this->due_date)->toDateString(),
            'status'             => $this->status,
            'sortOrder'          => (int) $this->sort_order,
            'completedAt'        => optional($this->completed_at)->toIso8601String(),
            'completedByUserId'  => $this->completed_by_user_id,
            'completionNotes'    => $this->completion_notes,
            'createdAt'          => optional($this->created_at)->toIso8601String(),
            'updatedAt'          => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
