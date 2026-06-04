<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\WorkSchedule;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves work schedules through Employee -> Department -> Global so a
 * tenant can declare the company default once, then patch only the days
 * that differ for a branch or a part-time employee.
 *
 * Resolution rules:
 *   - For a given (date, employee?) pair, find the most specific row for
 *     that ISO weekday: Employee override first, then the employee's
 *     Department, then the Global default.
 *   - Missing layers fall through cleanly; missing global rows trigger a
 *     synthetic fallback that mirrors the legacy `hrm.leave.standard_work_week`
 *     setting (Mon-Fri work days, 8 hours each) so removing the seeder
 *     doesn't break LeaveService duration counting.
 *
 * The resolved schedule is cached per request so a single
 * LeaveService::submitRequest computing duration across 30 days doesn't
 * re-issue 30 queries.
 */
class WorkScheduleService
{
    /** Default working hours (08:00-12:00 + 13:00-17:00). */
    public const DEFAULT_INTERVALS = [
        ['start' => '08:00', 'end' => '12:00'],
        ['start' => '13:00', 'end' => '17:00'],
    ];

    /** Saturday — half day. */
    public const DEFAULT_SATURDAY_INTERVALS = [
        ['start' => '08:00', 'end' => '12:00'],
    ];

    /**
     * @var array<string, WorkSchedule|null>
     *   Per-request memo keyed by "{targetType}:{targetId}:{dayOfWeek}".
     */
    private array $rowCache = [];

    /** @var array<string, array<int, WorkSchedule>>|null */
    private ?array $byTarget = null;

    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * Resolve the effective schedule for a single date.
     *
     * Returns ['is_work_day' => bool, 'intervals' => array, 'source' => string, 'schedule' => ?WorkSchedule].
     * `source` is one of 'employee' | 'department' | 'global' | 'default'
     * so callers (e.g. the UI) can surface which layer drove the answer.
     */
    public function resolveFor(CarbonImmutable $date, ?Employee $employee = null): array
    {
        $dow = (int) $date->dayOfWeekIso; // Carbon returns 1..7 (Mon..Sun)

        // 1. Employee override
        if ($employee) {
            $row = $this->rowFor(WorkSchedule::TARGET_EMPLOYEE, $employee->id, $dow);
            if ($row) {
                return $this->present($row, 'employee');
            }
            // 2. Department override
            if ($employee->department_id) {
                $row = $this->rowFor(WorkSchedule::TARGET_DEPARTMENT, $employee->department_id, $dow);
                if ($row) {
                    return $this->present($row, 'department');
                }
            }
        }

        // 3. Global default
        $row = $this->rowFor(WorkSchedule::TARGET_GLOBAL, null, $dow);
        if ($row) {
            return $this->present($row, 'global');
        }

        // 4. Legacy fallback - read `hrm.leave.standard_work_week` so a
        //    tenant who hasn't run the new seed yet still sees consistent
        //    behavior. Saturday gets default half-day intervals only when
        //    the legacy setting explicitly includes it.
        return $this->legacyFallback($dow);
    }

    /**
     * @return bool true when the resolved schedule for {date, employee?} is a work day.
     */
    public function isWorkDay(CarbonImmutable $date, ?Employee $employee = null): bool
    {
        return (bool) $this->resolveFor($date, $employee)['is_work_day'];
    }

    /**
     * Count working days in [start, end] inclusive for the given employee
     * (or the global default if employee is null). Half-day cases are not
     * applied here — that's a leave-session concern that LeaveService
     * keeps as a 0.5d adjustment.
     */
    public function countWorkingDays(CarbonImmutable $start, CarbonImmutable $end, ?Employee $employee = null): int
    {
        if ($end->lessThan($start)) {
            return 0;
        }

        $count = 0;
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            if ($this->isWorkDay($d, $employee)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * List every schedule row for a given target. Used by the editor UI to
     * paint all seven days at once. Returns a 7-row collection keyed by
     * day_of_week — missing days are synthesised as "off" placeholders so
     * the editor can save them as overrides on submit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function snapshotFor(string $targetType, ?string $targetId): Collection
    {
        $rows = WorkSchedule::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        $snapshot = collect();
        for ($dow = 1; $dow <= 7; $dow++) {
            $row = $rows->get($dow);
            $snapshot->push([
                'dayOfWeek'  => $dow,
                'isWorkDay'  => $row ? (bool) $row->is_work_day : false,
                'intervals'  => $row && is_array($row->intervals) ? $row->intervals : [],
                'id'         => $row?->id,
            ]);
        }
        return $snapshot;
    }

    /**
     * Bulk upsert seven rows for one target. Existing rows for that target
     * are replaced atomically so the editor can save the full week in one
     * round-trip. Validates target existence + interval shape.
     *
     * @param  array<int, array{dayOfWeek:int, isWorkDay:bool, intervals:array<int, array{start:string,end:string}>}>  $days
     */
    public function upsertWeek(string $targetType, ?string $targetId, array $days): Collection
    {
        $this->assertValidTarget($targetType, $targetId);

        $seen = [];
        foreach ($days as $row) {
            $dow = (int) ($row['dayOfWeek'] ?? 0);
            if ($dow < 1 || $dow > 7) {
                throw new DomainException("Invalid day_of_week: {$dow}. Expected 1..7 (ISO Mon..Sun).");
            }
            if (isset($seen[$dow])) {
                throw new DomainException("Duplicate day_of_week {$dow} in payload.");
            }
            $seen[$dow] = true;
            $this->assertValidIntervals($row['intervals'] ?? []);
        }

        $tenantId = tenant()?->getTenantKey();
        $now = now();

        $saved = collect();
        DB::transaction(function () use ($targetType, $targetId, $days, $tenantId, $now, &$saved) {
            foreach ($days as $row) {
                $dow = (int) $row['dayOfWeek'];
                $isWork = (bool) ($row['isWorkDay'] ?? false);
                $intervals = $isWork ? $this->normalizeIntervals($row['intervals'] ?? []) : [];

                $existing = WorkSchedule::query()
                    ->where('target_type', $targetType)
                    ->where('target_id', $targetId)
                    ->where('day_of_week', $dow)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'is_work_day' => $isWork,
                        'intervals'   => $intervals,
                    ]);
                    $saved->push($existing->fresh());
                } else {
                    $created = WorkSchedule::create([
                        'target_type' => $targetType,
                        'target_id'   => $targetId,
                        'day_of_week' => $dow,
                        'is_work_day' => $isWork,
                        'intervals'   => $intervals,
                        'tenant_id'   => $tenantId,
                    ]);
                    $saved->push($created);
                }
            }
        });

        $this->flushCache();
        return $saved;
    }

    /**
     * Clear ALL override rows for a target so it falls back to its parent
     * layer (e.g. removing every employee row restores the department or
     * global default). Used by the editor's "revert to default" affordance.
     */
    public function clearTarget(string $targetType, ?string $targetId): int
    {
        if ($targetType === WorkSchedule::TARGET_GLOBAL) {
            throw new DomainException('Global default cannot be cleared - reseed it instead.');
        }
        $deleted = WorkSchedule::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->delete();
        $this->flushCache();
        return $deleted;
    }

    /**
     * @return Collection<int, WorkSchedule>
     */
    public function listFor(string $targetType, ?string $targetId): Collection
    {
        return WorkSchedule::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('day_of_week')
            ->get();
    }

    public function flushCache(): void
    {
        $this->rowCache = [];
        $this->byTarget = null;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function rowFor(string $targetType, ?string $targetId, int $dow): ?WorkSchedule
    {
        $key = $targetType . ':' . ($targetId ?? '_') . ':' . $dow;
        if (array_key_exists($key, $this->rowCache)) {
            return $this->rowCache[$key];
        }

        // First lookup primes byTarget for the day so subsequent calls for
        // sibling weekdays of the same target reuse one query.
        $row = WorkSchedule::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('day_of_week', $dow)
            ->first();

        $this->rowCache[$key] = $row;
        return $row;
    }

    private function present(WorkSchedule $row, string $source): array
    {
        return [
            'is_work_day' => (bool) $row->is_work_day,
            'intervals'   => is_array($row->intervals) ? $row->intervals : [],
            'source'      => $source,
            'schedule'    => $row,
        ];
    }

    private function legacyFallback(int $dow): array
    {
        $workWeek = $this->settings->get('hrm.leave.standard_work_week');
        if (!is_array($workWeek)) {
            $workWeek = [1, 2, 3, 4, 5];
        }
        $workWeek = array_values(array_unique(array_map('intval', $workWeek)));

        $isWork = in_array($dow, $workWeek, true);

        return [
            'is_work_day' => $isWork,
            'intervals'   => $isWork ? self::DEFAULT_INTERVALS : [],
            'source'      => 'default',
            'schedule'    => null,
        ];
    }

    private function assertValidTarget(string $targetType, ?string $targetId): void
    {
        if (!in_array($targetType, WorkSchedule::TARGET_TYPES, true)) {
            throw new DomainException("Invalid target_type: {$targetType}.");
        }
        if ($targetType === WorkSchedule::TARGET_GLOBAL) {
            if ($targetId !== null) {
                throw new DomainException('Global schedule cannot have a target_id.');
            }
            return;
        }
        if ($targetId === null) {
            throw new DomainException('target_id is required for non-global schedules.');
        }
        $exists = match ($targetType) {
            WorkSchedule::TARGET_DEPARTMENT => Department::whereKey($targetId)->exists(),
            WorkSchedule::TARGET_EMPLOYEE   => Employee::whereKey($targetId)->exists(),
            default => false,
        };
        if (!$exists) {
            throw new DomainException("Target {$targetType} {$targetId} not found.");
        }
    }

    private function assertValidIntervals(array $intervals): void
    {
        $lastEnd = -1;
        foreach ($intervals as $i => $row) {
            if (!is_array($row)) {
                throw new DomainException("Interval #{$i} must be an object with start/end.");
            }
            $start = WorkSchedule::parseTime($row['start'] ?? null);
            $end   = WorkSchedule::parseTime($row['end'] ?? null);
            if ($start === null) {
                throw new DomainException("Interval #{$i} has invalid start time (expected HH:MM).");
            }
            if ($end === null) {
                throw new DomainException("Interval #{$i} has invalid end time (expected HH:MM).");
            }
            if ($end <= $start) {
                throw new DomainException("Interval #{$i} end must be after start.");
            }
            if ($start < $lastEnd) {
                throw new DomainException("Intervals must be non-overlapping and sorted by start time.");
            }
            $lastEnd = $end;
        }
    }

    private function normalizeIntervals(array $intervals): array
    {
        $out = [];
        foreach ($intervals as $row) {
            if (!is_array($row)) {
                continue;
            }
            $start = $row['start'] ?? null;
            $end   = $row['end'] ?? null;
            if (WorkSchedule::parseTime($start) === null || WorkSchedule::parseTime($end) === null) {
                continue;
            }
            $out[] = ['start' => $start, 'end' => $end];
        }
        usort($out, fn ($a, $b) => strcmp($a['start'], $b['start']));
        return $out;
    }
}
