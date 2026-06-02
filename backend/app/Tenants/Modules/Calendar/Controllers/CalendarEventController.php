<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Calendar\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CalendarEvent;
use App\Tenants\Modules\Calendar\Resources\CalendarEventResource;
use App\Tenants\Modules\Calendar\Services\CalendarEventService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Combined calendar feed + custom event CRUD.
 *
 *   GET    /calendar/events                  -> getCombinedEvents (union of 5 sources)
 *   POST   /calendar/events                  -> create custom event
 *   GET    /calendar/events/{event}          -> show single custom event
 *   PUT    /calendar/events/{event}          -> update custom event
 *   DELETE /calendar/events/{event}          -> delete custom event
 *
 * Self-scope rule: an employee without `calendar.event.read` (only
 * `.read.self`) is implicitly scoped to their own employee_id on the
 * combined feed. The resource layer additionally masks leave titles
 * when the actor lacks `hrm.leave.read`.
 */
class CalendarEventController extends Controller
{
    public function __construct(private readonly CalendarEventService $events)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'categories' => 'sometimes|array',
            'categories.*' => 'in:calendar,holiday,leave,shift,appointment',
            'employee_id' => 'sometimes|nullable|uuid',
            'branch_id' => 'sometimes|nullable|uuid',
        ]);

        $actor = $request->user();
        $canReadAll = (bool) $actor?->hasPermission('calendar.event.read');
        $actorEmployeeId = $actor?->employee?->id;

        // Self-only callers are forced to their own employee_id.
        $employeeId = $data['employee_id'] ?? null;
        if (!$canReadAll) {
            $employeeId = $actorEmployeeId;
        }

        try {
            $envelope = $this->events->getCombinedEvents(
                $data['from'],
                $data['to'],
                [
                    'categories' => $data['categories'] ?? CalendarEventService::ALL_SOURCES,
                    'employee_id' => $employeeId,
                    'branch_id' => $data['branch_id'] ?? null,
                ]
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $masked = array_map(
            fn (array $row) => (new CalendarEventResource($row))->toArray($request),
            $envelope['events'],
        );

        return response()->json([
            'data' => $masked,
            'meta' => [
                'from' => $envelope['from'],
                'to' => $envelope['to'],
                'count' => count($masked),
            ],
        ]);
    }

    public function show(CalendarEvent $event): CalendarEventResource
    {
        $this->authorize('view', $event);
        return new CalendarEventResource($event);
    }

    public function store(Request $request): CalendarEventResource|JsonResponse
    {
        $this->authorize('create', CalendarEvent::class);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'sometimes|nullable|string|max:2000',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'category' => 'sometimes|in:general,meeting,training,company,personal',
            'is_all_day' => 'sometimes|boolean',
            'employee_id' => 'sometimes|nullable|exists:employees,id',
        ]);

        // Self-scope users can only create events tied to themselves.
        $canWriteAll = (bool) $request->user()?->hasPermission('calendar.event.write');
        if (!$canWriteAll) {
            $data['employee_id'] = $request->user()?->employee?->id;
        }

        try {
            $event = $this->events->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new CalendarEventResource($event);
    }

    public function update(Request $request, CalendarEvent $event): CalendarEventResource|JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|nullable|string|max:2000',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after_or_equal:start_time',
            'category' => 'sometimes|in:general,meeting,training,company,personal',
            'is_all_day' => 'sometimes|boolean',
            'employee_id' => 'sometimes|nullable|exists:employees,id',
        ]);

        try {
            $event = $this->events->update($event, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new CalendarEventResource($event);
    }

    public function destroy(CalendarEvent $event): JsonResponse
    {
        $this->authorize('delete', $event);
        $this->events->destroy($event);
        return response()->json(['message' => 'Event removed.']);
    }
}
