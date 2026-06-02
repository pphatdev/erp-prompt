<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Holiday;
use App\Models\Tenant\Leave;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Holiday CRUD + calendar aggregation.
 *
 * The calendar feed merges holidays (expanding yearly recurrences) with
 * approved leaves so a single endpoint can render a mixed month view
 * without the UI doing two round-trips.
 */
class HolidayService
{
    public function buildQuery(): Builder
    {
        return Holiday::query()->orderBy('date');
    }

    public function create(array $data): Holiday
    {
        return DB::transaction(fn () => Holiday::create([
            'name'         => $data['name'],
            'date'         => $data['date'],
            'type'         => $data['type'] ?? Holiday::TYPE_PUBLIC,
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
            'notes'        => $data['notes'] ?? null,
        ]));
    }

    public function update(Holiday $holiday, array $data): Holiday
    {
        $holiday->fill(array_filter([
            'name'         => $data['name']         ?? null,
            'date'         => $data['date']         ?? null,
            'type'         => $data['type']         ?? null,
            'is_recurring' => array_key_exists('is_recurring', $data) ? (bool) $data['is_recurring'] : null,
            'notes'        => $data['notes']        ?? null,
        ], fn ($v) => $v !== null))->save();
        return $holiday;
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * Expand all holidays into concrete occurrences within [from, to].
     * Recurring entries fire on the same month/day every year in range.
     *
     * @return array<int, array{date:string, holiday:Holiday}>
     */
    public function occurrencesInRange(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end   = CarbonImmutable::parse($to)->endOfDay();

        if ($start->gt($end)) return [];

        $out = [];

        // Non-recurring: direct date-range filter.
        $nonRecurring = Holiday::query()
            ->where('is_recurring', false)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get();
        foreach ($nonRecurring as $h) {
            $out[] = [
                'date'    => optional($h->date)->toDateString(),
                'holiday' => $h,
            ];
        }

        // Recurring: pull all and expand the (month, day) anchor across the year span.
        $recurring = Holiday::query()->where('is_recurring', true)->get();
        foreach ($recurring as $h) {
            if (!$h->date) continue;
            $anchorMonth = (int) $h->date->format('n');
            $anchorDay   = (int) $h->date->format('j');

            for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
                // Feb 29 on non-leap years gets clamped to Feb 28 to keep the holiday observable.
                try {
                    $occ = CarbonImmutable::createFromFormat('!Y-n-j', "{$year}-{$anchorMonth}-{$anchorDay}");
                } catch (\Throwable) {
                    $occ = CarbonImmutable::create($year, $anchorMonth, 28);
                }
                if ($occ === false || $occ->lt($start) || $occ->gt($end)) continue;
                $out[] = [
                    'date'    => $occ->toDateString(),
                    'holiday' => $h,
                ];
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /**
     * Personal calendar feed: same holiday expansion + ONLY the given
     * employee's own leaves (any status, including drafts, so the user sees
     * their full schedule). When `$employeeId` is null the leaves list is
     * empty (auth user has no linked employee).
     *
     * @return array<string, mixed>
     */
    public function personalCalendarFeed(string $from, string $to, ?string $employeeId): array
    {
        $holidayOccurrences = $this->occurrencesInRange($from, $to);

        $leaves = collect();
        if ($employeeId) {
            $leaves = Leave::query()
                ->with(['leaveType'])
                ->where('employee_id', $employeeId)
                ->where(function ($q) use ($from, $to) {
                    $q->whereDate('start_date', '<=', $to)
                      ->whereDate('end_date',   '>=', $from);
                })
                ->orderBy('start_date')
                ->get();
        }

        return [
            'from'     => $from,
            'to'       => $to,
            'holidays' => array_map(fn ($r) => [
                'date'        => $r['date'],
                'id'          => $r['holiday']->id,
                'name'        => $r['holiday']->name,
                'type'        => $r['holiday']->type,
                'isRecurring' => (bool) $r['holiday']->is_recurring,
                'notes'       => $r['holiday']->notes,
            ], $holidayOccurrences),
            'personalLeaves' => $leaves->map(fn (Leave $l) => [
                'id'            => $l->id,
                'leaveTypeId'   => $l->leave_type_id,
                'leaveTypeName' => $l->leaveType?->name,
                'startDate'     => optional($l->start_date)->toDateString(),
                'endDate'       => optional($l->end_date)->toDateString(),
                'status'        => $l->status,
                'reason'        => $l->reason ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * Combined calendar feed used by the month view: expanded holidays plus
     * any approved or pending leaves that intersect the range.
     *
     * @return array<string, mixed>
     */
    public function calendarFeed(string $from, string $to): array
    {
        $holidayOccurrences = $this->occurrencesInRange($from, $to);

        $leaves = Leave::query()
            ->with(['employee', 'leaveType'])
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('start_date', '<=', $to)
                  ->whereDate('end_date',   '>=', $from);
            })
            ->whereIn('status', ['approved', 'pending'])
            ->orderBy('start_date')
            ->get();

        return [
            'from'     => $from,
            'to'       => $to,
            'holidays' => array_map(fn ($r) => [
                'date'        => $r['date'],
                'id'          => $r['holiday']->id,
                'name'        => $r['holiday']->name,
                'type'        => $r['holiday']->type,
                'isRecurring' => (bool) $r['holiday']->is_recurring,
                'notes'       => $r['holiday']->notes,
            ], $holidayOccurrences),
            'leaves'   => $leaves->map(fn (Leave $l) => [
                'id'           => $l->id,
                'employeeId'   => $l->employee_id,
                'employeeName' => $l->employee
                    ? trim(($l->employee->first_name ?? '') . ' ' . ($l->employee->last_name ?? ''))
                    : null,
                'leaveTypeId'   => $l->leave_type_id,
                'leaveTypeName' => $l->leaveType?->name,
                'startDate'    => optional($l->start_date)->toDateString(),
                'endDate'      => optional($l->end_date)->toDateString(),
                'status'       => $l->status,
            ])->values()->all(),
        ];
    }
}
