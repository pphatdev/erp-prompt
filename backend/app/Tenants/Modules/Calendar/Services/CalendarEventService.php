<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Calendar\Services;

use App\Models\Tenant\CalendarEvent;
use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\EmployeeShift;
use App\Models\Tenant\Leave;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Unified calendar query + custom-event CRUD.
 *
 *   getCombinedEvents($from, $to, $filters)
 *       Returns a flat array of events keyed by `source`:
 *         - 'calendar' - custom events from `calendar_events`
 *         - 'holiday'  - expanded holidays + Saturday/Sunday comp days
 *         - 'leave'    - approved+pending HRM leaves intersecting the range
 *         - 'shift'    - employee_shifts intersecting the range
 *         - 'appointment' - active CRM appointments intersecting the range
 *
 *       Enforces a 90-day max query window; rejects inverted ranges.
 *       Filters:
 *         - categories: array of source categories (default = all)
 *         - employee_id: scope to one employee across all sources
 *         - include_recurring: bool, drives whether recurring holidays are
 *           expanded (default true)
 *
 *       Privacy masking is NOT applied here - the resource layer
 *       (CalendarEventResource) hides sensitive titles based on the actor's
 *       hrm.leave.read permission.
 */
class CalendarEventService
{
    public const MAX_RANGE_DAYS = 90;

    public const SOURCE_CALENDAR = 'calendar';
    public const SOURCE_HOLIDAY = 'holiday';
    public const SOURCE_LEAVE = 'leave';
    public const SOURCE_SHIFT = 'shift';
    public const SOURCE_APPOINTMENT = 'appointment';
    public const ALL_SOURCES = [
        self::SOURCE_CALENDAR,
        self::SOURCE_HOLIDAY,
        self::SOURCE_LEAVE,
        self::SOURCE_SHIFT,
        self::SOURCE_APPOINTMENT,
    ];

    public function __construct(private readonly HolidayService $holidays)
    {
    }

    public function create(array $data): CalendarEvent
    {
        return DB::transaction(function () use ($data) {
            $this->assertChronological($data['start_time'] ?? null, $data['end_time'] ?? null);
            return CalendarEvent::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'category' => $data['category'] ?? CalendarEvent::CATEGORY_GENERAL,
                'is_all_day' => (bool) ($data['is_all_day'] ?? false),
                'employee_id' => $data['employee_id'] ?? null,
                'eventable_type' => $data['eventable_type'] ?? null,
                'eventable_id' => $data['eventable_id'] ?? null,
            ]);
        });
    }

    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data) {
            $start = $data['start_time'] ?? $event->start_time;
            $end = $data['end_time'] ?? $event->end_time;
            $this->assertChronological($start, $end);
            $event->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'category' => $data['category'] ?? null,
                'is_all_day' => array_key_exists('is_all_day', $data) ? (bool) $data['is_all_day'] : null,
                'employee_id' => array_key_exists('employee_id', $data) ? $data['employee_id'] : null,
            ], static fn ($v) => $v !== null))->save();
            return $event->fresh();
        });
    }

    public function destroy(CalendarEvent $event): void
    {
        $event->delete();
    }

    /**
     * @param array{
     *     categories?: array<int, string>,
     *     employee_id?: string|null,
     *     include_recurring?: bool,
     *     branch_id?: string|null,
     * } $filters
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     events: array<int, array<string, mixed>>
     * }
     */
    public function getCombinedEvents(string $from, string $to, array $filters = []): array
    {
        [$start, $end] = $this->validateRange($from, $to);
        $sources = $filters['categories'] ?? self::ALL_SOURCES;
        $employeeId = $filters['employee_id'] ?? null;
        $branchId = $filters['branch_id'] ?? null;

        $events = [];

        if (in_array(self::SOURCE_HOLIDAY, $sources, true)) {
            $events = array_merge($events, $this->projectHolidays($start, $end, $branchId));
        }
        if (in_array(self::SOURCE_CALENDAR, $sources, true)) {
            $events = array_merge($events, $this->projectCustomEvents($start, $end, $employeeId));
        }
        if (in_array(self::SOURCE_LEAVE, $sources, true)) {
            $events = array_merge($events, $this->projectLeaves($start, $end, $employeeId));
        }
        if (in_array(self::SOURCE_SHIFT, $sources, true)) {
            $events = array_merge($events, $this->projectShifts($start, $end, $employeeId));
        }
        if (in_array(self::SOURCE_APPOINTMENT, $sources, true)) {
            $events = array_merge($events, $this->projectAppointments($start, $end, $employeeId));
        }

        usort($events, fn ($a, $b) => strcmp((string) $a['startTime'], (string) $b['startTime']));

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'events' => $events,
        ];
    }

    private function projectHolidays(CarbonImmutable $start, CarbonImmutable $end, ?string $branchId): array
    {
        $rows = $this->holidays->applicableHolidaysInRange(
            $start->toDateString(),
            $end->toDateString(),
            $branchId,
        );
        $out = [];
        foreach ($rows as $row) {
            $h = $row['holiday'];
            $isComp = $row['compensatory_for'] !== null;
            $out[] = [
                'id' => $isComp ? "comp:{$h->id}:{$row['date']}" : $h->id,
                'source' => self::SOURCE_HOLIDAY,
                'category' => $h->type ?? 'public',
                'title' => $isComp ? "{$h->name} (compensatory)" : $h->name,
                'description' => $h->notes,
                'startTime' => $row['date'] . 'T00:00:00',
                'endTime' => $row['date'] . 'T23:59:59',
                'isAllDay' => true,
                'employeeId' => null,
                'meta' => [
                    'holidayId' => $h->id,
                    'isRecurring' => (bool) $h->is_recurring,
                    'compensatoryFor' => $row['compensatory_for'],
                    'overtimeMultiplier' => $h->overtime_multiplier !== null
                        ? (float) $h->overtime_multiplier
                        : null,
                    'branchId' => $h->branch_id,
                ],
            ];
        }
        return $out;
    }

    private function projectCustomEvents(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId): array
    {
        $q = CalendarEvent::query()
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<=', $end)
                  ->where('end_time', '>=', $start);
            });
        if ($employeeId !== null) {
            $q->where(function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                  ->orWhereNull('employee_id');
            });
        }
        return $q->orderBy('start_time')->get()->map(fn (CalendarEvent $e) => [
            'id' => $e->id,
            'source' => self::SOURCE_CALENDAR,
            'category' => $e->category,
            'title' => $e->title,
            'description' => $e->description,
            'startTime' => optional($e->start_time)->toIso8601String(),
            'endTime' => optional($e->end_time)->toIso8601String(),
            'isAllDay' => (bool) $e->is_all_day,
            'employeeId' => $e->employee_id,
            'meta' => [
                'eventableType' => $e->eventable_type,
                'eventableId' => $e->eventable_id,
            ],
        ])->all();
    }

    private function projectLeaves(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId): array
    {
        $q = Leave::query()
            ->with(['leaveType'])
            ->whereIn('status', ['approved', 'pending'])
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('start_date', '<=', $end->toDateString())
                  ->whereDate('end_date', '>=', $start->toDateString());
            });
        if ($employeeId !== null) {
            $q->where('employee_id', $employeeId);
        }
        return $q->orderBy('start_date')->get()->map(fn (Leave $l) => [
            'id' => $l->id,
            'source' => self::SOURCE_LEAVE,
            'category' => $l->leaveType?->name ?? 'leave',
            // CalendarEventResource hides this when the actor lacks hrm.leave.read.
            'title' => $l->leaveType?->name ?? 'Leave',
            'description' => $l->reason,
            'startTime' => optional($l->start_date)->toDateString() . 'T00:00:00',
            'endTime' => optional($l->end_date)->toDateString() . 'T23:59:59',
            'isAllDay' => true,
            'employeeId' => $l->employee_id,
            'meta' => [
                'leaveId' => $l->id,
                'status' => $l->status,
                'leaveTypeId' => $l->leave_type_id,
            ],
        ])->all();
    }

    private function projectShifts(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId): array
    {
        $q = EmployeeShift::query()
            ->with(['shift', 'employee'])
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('start_date', '<=', $end->toDateString())
                  ->whereDate('end_date', '>=', $start->toDateString());
            });
        if ($employeeId !== null) {
            $q->where('employee_id', $employeeId);
        }
        return $q->orderBy('start_date')->get()->map(fn (EmployeeShift $s) => [
            'id' => $s->id,
            'source' => self::SOURCE_SHIFT,
            'category' => 'shift',
            'title' => $s->shift?->name ?? 'Shift',
            'description' => null,
            'startTime' => optional($s->start_date)->toDateString() . 'T00:00:00',
            'endTime' => optional($s->end_date)->toDateString() . 'T23:59:59',
            'isAllDay' => true,
            'employeeId' => $s->employee_id,
            'meta' => [
                'shiftId' => $s->shift_id,
                'employeeShiftId' => $s->id,
            ],
        ])->all();
    }

    private function projectAppointments(CarbonImmutable $start, CarbonImmutable $end, ?string $employeeId): array
    {
        $q = CrmAppointment::query()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where(function ($q) use ($start, $end) {
                $q->where('starts_at', '<=', $end)
                  ->where('ends_at', '>=', $start);
            });
        if ($employeeId !== null) {
            $q->where('actor_id', $employeeId);
        }
        return $q->orderBy('starts_at')->get()->map(fn (CrmAppointment $a) => [
            'id' => $a->id,
            'source' => self::SOURCE_APPOINTMENT,
            'category' => 'meeting',
            'title' => $a->subject,
            'description' => $a->notes,
            'startTime' => optional($a->starts_at)->toIso8601String(),
            'endTime' => optional($a->ends_at)->toIso8601String(),
            'isAllDay' => false,
            'employeeId' => $a->actor_id,
            'meta' => [
                'appointmentId' => $a->id,
                'leadId' => $a->lead_id,
                'opportunityId' => $a->opportunity_id,
                'location' => $a->location,
            ],
        ])->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function validateRange(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        if ($start->gt($end)) {
            throw new DomainException('Calendar range start must be on or before the end date.');
        }
        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            throw new DomainException(
                sprintf('Calendar range cannot exceed %d days (got %d).', self::MAX_RANGE_DAYS, (int) $start->diffInDays($end))
            );
        }
        return [$start, $end];
    }

    private function assertChronological(mixed $start, mixed $end): void
    {
        if ($start === null || $end === null) {
            return;
        }
        $s = CarbonImmutable::parse($start);
        $e = CarbonImmutable::parse($end);
        if ($s->gt($e)) {
            throw new DomainException('Event start_time must be on or before end_time.');
        }
    }
}
