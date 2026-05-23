<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\EmployeeShift;
use App\Models\Tenant\Shift;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = Shift::query()->orderBy('name');

        if (!empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';
            $query->where('name', 'ilike', $term);
        }

        return $query;
    }

    public function createShift(array $data): Shift
    {
        $this->assertWindowValid($data['start_time'], $data['end_time']);

        return Shift::create($data);
    }

    public function updateShift(Shift $shift, array $data): Shift
    {
        $start = $data['start_time'] ?? $shift->start_time;
        $end   = $data['end_time']   ?? $shift->end_time;
        $this->assertWindowValid($start, $end);

        $shift->update($data);

        return $shift->fresh();
    }

    /**
     * Assign an employee to a shift starting on a given date. Closes any
     * currently-open assignment for the same employee by stamping its
     * end_date to the day before the new start_date — keeps the history
     * traversable for reconciliation without gaps.
     */
    public function assignToEmployee(string $employeeId, string $shiftId, string $startDate, ?string $endDate = null): EmployeeShift
    {
        $start = CarbonImmutable::parse($startDate);
        $end   = $endDate ? CarbonImmutable::parse($endDate) : null;

        if ($end !== null && $end->lt($start)) {
            throw new DomainException('end_date must be on or after start_date.');
        }

        return DB::transaction(function () use ($employeeId, $shiftId, $start, $end) {
            // Close any currently-open assignment so reconciliation has a clear
            // owner per date. We don't *prevent* an admin from overlapping
            // ranges explicitly — they can pass end_date in if they want that.
            EmployeeShift::query()
                ->where('employee_id', $employeeId)
                ->whereNull('end_date')
                ->where('start_date', '<', $start->toDateString())
                ->update(['end_date' => $start->subDay()->toDateString()]);

            return EmployeeShift::create([
                'employee_id' => $employeeId,
                'shift_id'    => $shiftId,
                'start_date'  => $start->toDateString(),
                'end_date'    => $end?->toDateString(),
            ]);
        });
    }

    /**
     * Resolve the active shift for an employee on a given date.
     * Returns null when no assignment covers the date — caller decides
     * whether that's "off-shift" or an absence per business rules.
     */
    public function activeShiftFor(string $employeeId, string $date): ?Shift
    {
        $assignment = EmployeeShift::query()
            ->where('employee_id', $employeeId)
            ->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('start_date')
            ->first();

        return $assignment?->shift;
    }

    private function assertWindowValid(string $startTime, string $endTime): void
    {
        // Same-day shifts only for now — overnight handling (end < start) is
        // a deliberate non-goal until reconciliation supports day-boundary
        // splits. Reject explicitly so we don't silently produce 0-hour days.
        if (strcmp($endTime, $startTime) <= 0) {
            throw new DomainException('end_time must be after start_time (overnight shifts not yet supported).');
        }
    }
}
