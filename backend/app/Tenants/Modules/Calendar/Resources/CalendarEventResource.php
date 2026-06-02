<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Calendar\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a single combined-feed event row produced by
 * CalendarEventService::getCombinedEvents OR for a stored CalendarEvent
 * model passed in raw.
 *
 * The Service returns arrays already shaped to the final envelope; this
 * resource layers privacy masking on top:
 *
 *   - When source = 'leave' and the actor does NOT hold `hrm.leave.read`,
 *     the title is replaced with "Leave - Confirmed" and the description
 *     is hidden. The employeeId is also hidden unless the actor either
 *     holds `hrm.leave.read` or is the leave's owner.
 *
 *   - When source = 'calendar' AND the event has category = 'personal'
 *     and the actor is neither the owner nor holds `calendar.event.read`,
 *     the title is replaced with "Personal event".
 */
class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Accept either a model instance or an array (combined-feed row).
        $payload = is_array($this->resource) ? $this->resource : $this->modelToArray();

        $actor = $request->user();
        $actorEmployeeId = $actor?->employee?->id;
        $canSeeLeaveDetail = (bool) $actor?->hasPermission('hrm.leave.read');
        $canSeeAllEvents = (bool) $actor?->hasPermission('calendar.event.read');

        $source = $payload['source'] ?? 'calendar';
        $title = $payload['title'] ?? '';
        $description = $payload['description'] ?? null;
        $employeeId = $payload['employeeId'] ?? null;

        if ($source === 'leave' && !$canSeeLeaveDetail) {
            $isOwn = $employeeId !== null && $employeeId === $actorEmployeeId;
            if (!$isOwn) {
                $title = 'Leave - Confirmed';
                $description = null;
                $employeeId = null;
            }
        }

        if ($source === 'calendar' && ($payload['category'] ?? null) === 'personal') {
            $isOwn = $employeeId !== null && $employeeId === $actorEmployeeId;
            if (!$isOwn && !$canSeeAllEvents) {
                $title = 'Personal event';
                $description = null;
            }
        }

        return [
            'id' => $payload['id'] ?? null,
            'source' => $source,
            'category' => $payload['category'] ?? null,
            'title' => $title,
            'description' => $description,
            'startTime' => $payload['startTime'] ?? null,
            'endTime' => $payload['endTime'] ?? null,
            'isAllDay' => (bool) ($payload['isAllDay'] ?? false),
            'employeeId' => $employeeId,
            'meta' => $payload['meta'] ?? [],
        ];
    }

    private function modelToArray(): array
    {
        /** @var \App\Models\Tenant\CalendarEvent $model */
        $model = $this->resource;
        return [
            'id' => $model->id,
            'source' => 'calendar',
            'category' => $model->category,
            'title' => $model->title,
            'description' => $model->description,
            'startTime' => optional($model->start_time)->toIso8601String(),
            'endTime' => optional($model->end_time)->toIso8601String(),
            'isAllDay' => (bool) $model->is_all_day,
            'employeeId' => $model->employee_id,
            'meta' => [
                'eventableType' => $model->eventable_type,
                'eventableId' => $model->eventable_id,
            ],
        ];
    }
}
