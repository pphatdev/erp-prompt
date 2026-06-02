<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Calendar\Services;

use App\Models\Tenant\Holiday;
use App\Tenants\Modules\HRM\Services\HolidayService as HrmHolidayService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;

/**
 * Calendar-side holiday helpers.
 *
 * Does NOT duplicate the HRM HolidayService CRUD - that one still owns
 * create/update/destroy + the raw `occurrencesInRange` expansion. This
 * service layers calendar-specific concerns on top:
 *
 *   getCompensatoryDay(Holiday)
 *       Returns the next Monday after a holiday that falls on Sat/Sun,
 *       or null when:
 *         - calendar.compensatory_day setting is false,
 *         - the holiday is not on a weekend for the resolved year, or
 *         - the holiday has no date.
 *
 *   applicableHolidaysInRange($from, $to, ?$branchId)
 *       Returns occurrences from HRM's expansion plus the virtual
 *       compensatory days that fall inside [from, to]. Each row carries
 *       a `compensatory_for` field (null for the actual holiday, the
 *       source Holiday id for the virtual Monday). Branch filtering
 *       passes through `holidays.branch_id` (NULL = all branches).
 *
 *   checkIsHoliday($date, ?$branchId)
 *       True when any holiday or compensatory day matches the exact date.
 *       Used by HRM/OvertimeService and the attendance reconciler.
 */
class HolidayService
{
    public function __construct(
        private readonly HrmHolidayService $hrm,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * @return CarbonImmutable|null  The Monday after the weekend holiday,
     *                               or null when comp-day generation does
     *                               not apply.
     */
    public function getCompensatoryDay(Holiday $holiday, ?int $year = null): ?CarbonImmutable
    {
        if (!$this->compensatoryEnabled()) {
            return null;
        }
        if (!$holiday->date) {
            return null;
        }

        $resolvedYear = $year ?? (int) date('Y');
        $holidayDate = $holiday->resolveDateForYear($resolvedYear);
        $dow = (int) $holidayDate->format('N'); // 6=Sat, 7=Sun

        return match ($dow) {
            6 => $holidayDate->next('Monday'), // Saturday -> next Monday (2 days)
            7 => $holidayDate->next('Monday'), // Sunday -> next Monday (1 day)
            default => null,
        };
    }

    /**
     * @return array<int, array{
     *     date: string,
     *     holiday: Holiday,
     *     compensatory_for: ?string
     * }>
     */
    public function applicableHolidaysInRange(string $from, string $to, ?string $branchId = null): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();

        // Delegate the base expansion to HRM. Result rows are already shaped
        // as ['date' => 'Y-m-d', 'holiday' => Holiday].
        $base = $this->hrm->occurrencesInRange($from, $to);

        // Branch filter on the source holiday (NULL branch = applies everywhere).
        if ($branchId !== null) {
            $base = array_values(array_filter(
                $base,
                fn ($r) => $r['holiday']->branch_id === null || $r['holiday']->branch_id === $branchId
            ));
        }

        $out = [];
        foreach ($base as $row) {
            $out[] = [
                'date' => $row['date'],
                'holiday' => $row['holiday'],
                'compensatory_for' => null,
            ];

            // Mint a comp-day if applicable and it falls inside the range.
            $occurrenceYear = (int) CarbonImmutable::parse($row['date'])->format('Y');
            $comp = $this->getCompensatoryDay($row['holiday'], $occurrenceYear);
            if ($comp && $comp->gte($start) && $comp->lte($end)) {
                $out[] = [
                    'date' => $comp->toDateString(),
                    'holiday' => $row['holiday'],
                    'compensatory_for' => $row['holiday']->id,
                ];
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    public function checkIsHoliday(string $date, ?string $branchId = null): bool
    {
        $rows = $this->applicableHolidaysInRange($date, $date, $branchId);
        return $rows !== [];
    }

    private function compensatoryEnabled(): bool
    {
        return (bool) $this->settings->get('calendar.compensatory_day', true);
    }
}
