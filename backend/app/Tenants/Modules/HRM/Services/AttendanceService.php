<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Models\Tenant\Shift;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Default geofence radius when a department has lat/lon set but no
     * explicit radius. Matches the spec's 100m baseline.
     */
    public const DEFAULT_GEOFENCE_RADIUS_METERS = 100;

    /**
     * Default auto-close window (hours) used when the tenant hasn't
     * customised `hrm.attendance.auto_clock_out_hours`.
     */
    private const DEFAULT_AUTO_CLOCK_OUT_HOURS = 12;

    /**
     * Earth radius (mean) in metres — used by the Haversine distance check.
     */
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly SettingService $settings,
        private readonly WorkScheduleService $workSchedules,
    ) {
    }

    /**
     * Aggregate attendance counts over a date range — consumed by
     * PayrollService::computeFor() to apply absence-based deductions.
     *
     * @return array{absent:int, unpaidLeave:int, paidLeave:int, halfDay:int, present:int, late:int}
     */
    public function summaryFor(string $employeeId, string $from, string $to): array
    {
        $rows = AttendanceLog::query()
            ->selectRaw('status, COUNT(*) as count')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$from, $to])
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'absent'      => (int) ($rows[AttendanceLog::STATUS_ABSENT] ?? 0),
            'unpaidLeave' => (int) ($rows[AttendanceLog::STATUS_UNPAID_LEAVE] ?? 0),
            'paidLeave'   => (int) ($rows[AttendanceLog::STATUS_PAID_LEAVE] ?? 0),
            'halfDay'     => (int) ($rows[AttendanceLog::STATUS_HALF_DAY] ?? 0),
            'present'     => (int) ($rows[AttendanceLog::STATUS_PRESENT] ?? 0),
            'late'        => (int) ($rows[AttendanceLog::STATUS_LATE] ?? 0),
        ];
    }

    /**
     * Reconcile a single date — fills in the gap rows the daily job needs:
     *   - existing clock-in row: leave as-is (status already resolved)
     *   - approved leave covers the date: paid_leave / unpaid_leave per type
     *   - Sat/Sun: weekend
     *   - otherwise: absent
     *
     * Idempotent: skips rows that already exist. Returns the resolved status
     * for the date (or null when a row was already present).
     */
    public function reconcileDate(Employee $employee, string $date): ?string
    {
        $existing = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($existing) {
            return null;   // Already has a row — clock-in or prior reconcile.
        }

        $status = $this->classifyDate($employee, $date);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => $status,
        ]);

        return $status;
    }

    /**
     * Reconcile every active employee for a single date — invoked by the
     * daily 01:00 cron over the previous day, or manually via the admin
     * endpoint. Returns counts so the trigger can render a summary.
     *
     * @return array{processed:int, created:int, skipped:int}
     */
    public function reconcileAll(string $date): array
    {
        // Phase 9: close any clock-ins that are still hanging open beyond
        // the configured auto-close window before classifying missing-day
        // rows. Without this the operator would see absences for employees
        // who clocked in yesterday but never out, masking the real signal.
        $autoClosed = $this->autoCloseOpenSessions($date);

        $employees = Employee::query()->where('status', 'active')->get();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($employees, $date, &$created, &$skipped) {
            foreach ($employees as $employee) {
                $status = $this->reconcileDate($employee, $date);
                $status === null ? $skipped++ : $created++;
            }
        });

        return [
            'processed'  => $employees->count(),
            'created'    => $created,
            'skipped'    => $skipped,
            'autoClosed' => $autoClosed,
        ];
    }

    /**
     * Phase 9: close open clock-ins that have been hanging open longer than
     * `hrm.attendance.auto_clock_out_hours`. Each closed row gets
     * `check_out = check_in + window_hours` so the duration is bounded
     * (we don't claim the employee was present indefinitely). Returns the
     * number of rows mutated.
     *
     * Scope: only rows whose `date` is on or before `$asOf` (defaults to
     * today). This lets the daily reconcile pass close yesterday's
     * stragglers without touching today's still-active sessions.
     */
    public function autoCloseOpenSessions(?string $asOf = null): int
    {
        $rawHours = $this->settings->get('hrm.attendance.auto_clock_out_hours');
        $windowHours = is_numeric($rawHours) ? max(1, (int) $rawHours) : self::DEFAULT_AUTO_CLOCK_OUT_HOURS;
        $now = CarbonImmutable::now();
        $cutoffDate = $asOf ?? $now->toDateString();

        $open = AttendanceLog::query()
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->where('date', '<=', $cutoffDate)
            ->get();

        if ($open->isEmpty()) {
            return 0;
        }

        $closed = 0;
        DB::transaction(function () use ($open, $now, $windowHours, &$closed) {
            foreach ($open as $log) {
                $checkIn = CarbonImmutable::parse($log->check_in);
                $elapsed = $checkIn->diffInHours($now);
                if ($elapsed < $windowHours) {
                    continue;
                }
                $log->update([
                    'check_out' => $checkIn->addHours($windowHours),
                ]);
                $closed++;
            }
        });

        return $closed;
    }

    /**
     * Classify a missing-clock-in date — leave > non-work-day > absent.
     * Non-work-day resolution uses the hierarchical WorkScheduleService
     * (employee -> department -> global -> legacy `standard_work_week`),
     * so a Sat half-day or a Sun-Thu weekly arrangement classifies
     * correctly for the employee being reconciled.
     */
    private function classifyDate(Employee $employee, string $date): string
    {
        $approvedLeave = Leave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with('leaveType')
            ->first();

        if ($approvedLeave) {
            // No paid/unpaid flag on LeaveType yet — treat all approved leave
            // as paid by default. When the LeaveType.is_paid column lands,
            // gate this branch on $approvedLeave->leaveType->is_paid.
            return AttendanceLog::STATUS_PAID_LEAVE;
        }

        $isWorkDay = $this->workSchedules->isWorkDay(
            CarbonImmutable::parse($date),
            $employee,
        );
        if (!$isWorkDay) {
            return AttendanceLog::STATUS_WEEKEND;
        }

        return AttendanceLog::STATUS_ABSENT;
    }

    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = AttendanceLog::query()->with('employee');

        if (!empty($filters['employeeId'])) {
            $query->where('employee_id', $filters['employeeId']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('date', '<=', $filters['to']);
        }

        return $query->orderByDesc('date');
    }

    /**
     * Record a clock-in for the given employee.
     *
     * Steps (matching skills/hrm/time_off_attendance/flow.md §1):
     *   1. IP whitelist enforcement (when configured on the department).
     *   2. Geofence enforcement via Haversine (when lat/lon configured).
     *   3. Resolve attendance status against the active shift's grace
     *      period and half-day threshold (defaults to PRESENT when no
     *      shift is assigned — admins can correct via the manual edit
     *      endpoint shipped in a follow-up slice).
     *   4. Upsert a row in attendance_logs for the resolved calendar day.
     *
     * Throws DomainException on duplicate clock-ins; ValidationException
     * (mapped to 422) on IP / geofence violations.
     */
    public function clockIn(Employee $employee, array $data): AttendanceLog
    {
        $now = isset($data['clock_time'])
            ? CarbonImmutable::parse($data['clock_time'])
            : CarbonImmutable::now();

        $date = $now->toDateString();

        $department = $employee->department;
        $this->enforceIpWhitelist($department, $data['client_ip'] ?? null);
        $this->enforceGeofence(
            $department,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
        );

        $shift  = $this->shifts->activeShiftFor($employee->id, $date);
        $status = $this->resolveClockInStatus($shift, $now);

        return DB::transaction(function () use ($employee, $now, $date, $data, $status) {
            $existing = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $date)
                ->first();

            if ($existing && $existing->check_in !== null) {
                throw new DomainException('Already clocked in for this date.');
            }

            if ($existing) {
                // Reconciliation may have pre-seeded an "absent" row; convert
                // it into the resolved clock-in instead of failing the unique
                // partial index.
                $existing->update([
                    'check_in' => $now,
                    'status' => $status,
                    'check_in_ip' => $data['client_ip'] ?? null,
                    'check_in_lat' => $data['latitude'] ?? null,
                    'check_in_lon' => $data['longitude'] ?? null,
                ]);
                return $existing->fresh();
            }

            return AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'check_in' => $now,
                'status' => $status,
                'check_in_ip' => $data['client_ip'] ?? null,
                'check_in_lat' => $data['latitude'] ?? null,
                'check_in_lon' => $data['longitude'] ?? null,
            ]);
        });
    }

    /**
     * Record a clock-out for the open log on this date. Promotes the status
     * to EARLY_OUT when the clock-out predates the shift's end_time.
     */
    public function clockOut(Employee $employee, array $data): AttendanceLog
    {
        $now = isset($data['clock_time'])
            ? CarbonImmutable::parse($data['clock_time'])
            : CarbonImmutable::now();

        $date = $now->toDateString();

        $department = $employee->department;
        $this->enforceIpWhitelist($department, $data['client_ip'] ?? null);
        $this->enforceGeofence(
            $department,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
        );

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if (!$log || $log->check_in === null) {
            throw new DomainException('No active clock-in found for today — call /clock-in first.');
        }

        if ($log->check_out !== null) {
            throw new DomainException('Already clocked out for this date.');
        }

        $shift  = $this->shifts->activeShiftFor($employee->id, $date);
        $status = $this->resolveClockOutStatus($log->status, $shift, $now);

        $log->update([
            'check_out' => $now,
            'status' => $status,
            'check_out_ip' => $data['client_ip'] ?? null,
            'check_out_lat' => $data['latitude'] ?? null,
            'check_out_lon' => $data['longitude'] ?? null,
        ]);

        return $log->fresh();
    }

    /**
     * Resolve clock-in status against shift grace boundaries:
     *   - <= shift.start_time + grace_period_minutes      → PRESENT
     *   - <= shift.start_time + half_day_threshold_minutes → LATE
     *   -  > shift.start_time + half_day_threshold_minutes → HALF_DAY
     *
     * When no shift is assigned we record PRESENT so the row still lands —
     * admins can correct via manual edit if they later assign a shift.
     */
    private function resolveClockInStatus(?Shift $shift, CarbonImmutable $clockTime): string
    {
        if ($shift === null) {
            return AttendanceLog::STATUS_PRESENT;
        }

        $shiftStart = CarbonImmutable::parse(
            $clockTime->toDateString() . ' ' . $shift->start_time,
            $clockTime->timezone,
        );

        $grace     = max(0, (int) $shift->grace_period_minutes);
        $halfDay   = $shift->half_day_threshold_minutes !== null
            ? (int) $shift->half_day_threshold_minutes
            : null;

        $lateAfter     = $shiftStart->addMinutes($grace);
        $halfDayAfter  = $halfDay !== null ? $shiftStart->addMinutes($halfDay) : null;

        if ($clockTime->lessThanOrEqualTo($lateAfter)) {
            return AttendanceLog::STATUS_PRESENT;
        }

        if ($halfDayAfter !== null && $clockTime->greaterThan($halfDayAfter)) {
            return AttendanceLog::STATUS_HALF_DAY;
        }

        return AttendanceLog::STATUS_LATE;
    }

    /**
     * Clock-out only flips PRESENT/LATE rows to EARLY_OUT — HALF_DAY rows
     * already capture the full-day classification and shouldn't be
     * downgraded, even if the employee leaves early.
     */
    private function resolveClockOutStatus(string $current, ?Shift $shift, CarbonImmutable $clockTime): string
    {
        if ($shift === null) {
            return $current;
        }

        if (!in_array($current, [AttendanceLog::STATUS_PRESENT, AttendanceLog::STATUS_LATE], true)) {
            return $current;
        }

        $shiftEnd = CarbonImmutable::parse(
            $clockTime->toDateString() . ' ' . $shift->end_time,
            $clockTime->timezone,
        );

        if ($clockTime->lessThan($shiftEnd)) {
            return AttendanceLog::STATUS_EARLY_OUT;
        }

        return $current;
    }

    /**
     * Phase 9: IP enforcement gates on `hrm.attendance.enable_ip_whitelisting`.
     * When OFF (default), no enforcement runs — even if a department row
     * still carries legacy `attendance_ip_whitelist` values; tenants must
     * opt-in via the setting. When ON, the effective rule set is the union
     * of the tenant-level `hrm.attendance.ip_whitelist` (comma-separated)
     * and the per-department whitelist; an empty union becomes an open
     * fail-closed (we refuse the clock-in to avoid a silent allow-all when
     * the operator clearly intended enforcement).
     */
    private function enforceIpWhitelist(?Department $department, ?string $ip): void
    {
        if (!(bool) $this->settings->get('hrm.attendance.enable_ip_whitelisting')) {
            return;
        }

        $rules = $this->resolveIpWhitelistRules($department);
        if (empty($rules)) {
            $this->fail('client_ip', 'IP whitelisting is enabled but no rules are configured.');
        }

        if ($ip === null) {
            $this->fail('client_ip', 'Client IP is required for clock-in.');
        }

        foreach ($rules as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return;
            }
        }

        $this->fail('client_ip', 'Your network is not authorised for clock-in.');
    }

    /**
     * Union of tenant-level `hrm.attendance.ip_whitelist` and the legacy
     * per-department list. Defensive: strings get split on commas;
     * empty / whitespace entries dropped.
     */
    private function resolveIpWhitelistRules(?Department $department): array
    {
        $rules = [];

        $tenantRaw = $this->settings->get('hrm.attendance.ip_whitelist');
        if (is_string($tenantRaw) && $tenantRaw !== '') {
            foreach (explode(',', $tenantRaw) as $entry) {
                $entry = trim($entry);
                if ($entry !== '') {
                    $rules[] = $entry;
                }
            }
        } elseif (is_array($tenantRaw)) {
            foreach ($tenantRaw as $entry) {
                $entry = is_string($entry) ? trim($entry) : '';
                if ($entry !== '') {
                    $rules[] = $entry;
                }
            }
        }

        $deptRules = $department?->attendance_ip_whitelist;
        if (is_array($deptRules)) {
            foreach ($deptRules as $entry) {
                $entry = is_string($entry) ? trim($entry) : '';
                if ($entry !== '') {
                    $rules[] = $entry;
                }
            }
        }

        return array_values(array_unique($rules));
    }

    /**
     * Phase 9: geofence enforcement gates on `hrm.attendance.enable_geofencing`.
     * When OFF (default), no GPS check runs — tenants opt-in via the
     * setting. When ON: a department-attached lat/lon is required to
     * compute the office origin; without it we skip rather than reject
     * (an enforcement-without-origin can't be evaluated). Radius falls
     * back from the department override → tenant setting → 100m default.
     */
    private function enforceGeofence(?Department $department, ?float $lat, ?float $lon): void
    {
        if (!(bool) $this->settings->get('hrm.attendance.enable_geofencing')) {
            return;
        }

        if ($department === null) {
            return;
        }

        $officeLat = $department->latitude;
        $officeLon = $department->longitude;
        if ($officeLat === null || $officeLon === null) {
            return;
        }

        if ($lat === null || $lon === null) {
            $this->fail('latitude', 'Latitude/longitude required for clock-in.');
        }

        $tenantRadius = $this->settings->get('hrm.attendance.geofence_radius_meters');
        $radius = (int) ($department->geofence_radius_meters
            ?? (is_numeric($tenantRadius) ? (int) $tenantRadius : self::DEFAULT_GEOFENCE_RADIUS_METERS));

        $distance = $this->haversineMeters((float) $officeLat, (float) $officeLon, $lat, $lon);

        if ($distance > $radius) {
            $this->fail('latitude', sprintf(
                'Out of geofence bounds — %dm from office (limit %dm).',
                (int) round($distance),
                $radius,
            ));
        }
    }

    /**
     * Great-circle distance between two coordinates in metres. Standard
     * Haversine — sufficient for office-scale radii (<10km).
     */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latRad1 = deg2rad($lat1);
        $latRad2 = deg2rad($lat2);
        $dLat    = deg2rad($lat2 - $lat1);
        $dLon    = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos($latRad1) * cos($latRad2) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Match a single IP against a CIDR range. Supports plain IPs ("1.2.3.4"
     * with no prefix) and IPv4 CIDR ("1.2.3.0/24"). IPv6 is left as-is —
     * tenants needing v6 whitelisting should configure server-side.
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
