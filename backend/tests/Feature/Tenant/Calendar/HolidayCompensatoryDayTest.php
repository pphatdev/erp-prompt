<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Calendar;

use App\Models\Tenant\Holiday;
use App\Models\Tenant\Setting;
use App\Tenants\Modules\Calendar\Services\HolidayService;
use Tests\Feature\TenantTestCase;

/**
 * P1 - HolidayService comp-day generation.
 *
 *   - Sat/Sun holidays produce a virtual Monday compensatory day.
 *   - Weekday holidays produce no comp day.
 *   - `calendar.compensatory_day` setting gates the behavior.
 *   - `applicableHolidaysInRange` appends comp days inside the window,
 *     drops them when they fall outside, and tags the source via
 *     `compensatory_for`.
 *   - `checkIsHoliday` returns true for both the original day and its
 *     comp Monday (when comp-day is on).
 */
class HolidayCompensatoryDayTest extends TenantTestCase
{
    private HolidayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HolidayService::class);
    }

    private function enableCompensatory(bool $on): void
    {
        Setting::updateOrCreate(
            ['key' => 'calendar.compensatory_day'],
            ['value' => $on, 'group' => 'calendar', 'type' => 'boolean']
        );
    }

    public function test_saturday_holiday_produces_monday_compensatory_day(): void
    {
        $this->enableCompensatory(true);
        // 2026-01-03 is a Saturday.
        $h = Holiday::create([
            'name' => 'Founders Day',
            'date' => '2026-01-03',
            'type' => Holiday::TYPE_COMPANY,
            'is_recurring' => false,
        ]);

        $comp = $this->service->getCompensatoryDay($h);
        $this->assertNotNull($comp);
        $this->assertSame('2026-01-05', $comp->toDateString(), 'Sat -> next Monday');
    }

    public function test_sunday_holiday_produces_monday_compensatory_day(): void
    {
        $this->enableCompensatory(true);
        // 2026-01-04 is a Sunday.
        $h = Holiday::create([
            'name' => 'Spring Festival',
            'date' => '2026-01-04',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        $comp = $this->service->getCompensatoryDay($h);
        $this->assertNotNull($comp);
        $this->assertSame('2026-01-05', $comp->toDateString(), 'Sun -> next Monday');
    }

    public function test_weekday_holiday_produces_no_compensatory_day(): void
    {
        $this->enableCompensatory(true);
        // 2026-01-07 is a Wednesday.
        $h = Holiday::create([
            'name' => 'Mid-week Recognition',
            'date' => '2026-01-07',
            'type' => Holiday::TYPE_COMPANY,
            'is_recurring' => false,
        ]);

        $this->assertNull($this->service->getCompensatoryDay($h));
    }

    public function test_setting_disabled_suppresses_compensatory_day(): void
    {
        $this->enableCompensatory(false);
        $h = Holiday::create([
            'name' => 'Weekend Day',
            'date' => '2026-01-03', // Saturday
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        $this->assertNull($this->service->getCompensatoryDay($h));
    }

    public function test_applicable_range_appends_comp_day_inside_window(): void
    {
        $this->enableCompensatory(true);
        Holiday::create([
            'name' => 'Saturday Public Holiday',
            'date' => '2026-01-03',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        $rows = $this->service->applicableHolidaysInRange('2026-01-01', '2026-01-10');

        // Two rows expected: the original Saturday + the compensatory Monday.
        $dates = array_column($rows, 'date');
        $this->assertContains('2026-01-03', $dates);
        $this->assertContains('2026-01-05', $dates);

        $compRow = collect($rows)->firstWhere('date', '2026-01-05');
        $this->assertNotNull($compRow['compensatory_for'],
            'Comp Monday must carry the source holiday id in compensatory_for.');

        $originalRow = collect($rows)->firstWhere('date', '2026-01-03');
        $this->assertNull($originalRow['compensatory_for'],
            'Original Saturday row must NOT have compensatory_for set.');
    }

    public function test_applicable_range_drops_comp_day_outside_window(): void
    {
        $this->enableCompensatory(true);
        Holiday::create([
            'name' => 'Saturday Public Holiday',
            'date' => '2026-01-03',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        // Window cuts off at Sat - Monday comp would be the 5th, out of range.
        $rows = $this->service->applicableHolidaysInRange('2026-01-01', '2026-01-03');

        $dates = array_column($rows, 'date');
        $this->assertContains('2026-01-03', $dates);
        $this->assertNotContains('2026-01-05', $dates,
            'Comp Monday is outside the query range and must not appear.');
    }

    public function test_check_is_holiday_returns_true_on_both_original_and_comp_day(): void
    {
        $this->enableCompensatory(true);
        Holiday::create([
            'name' => 'Saturday Public Holiday',
            'date' => '2026-01-03',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => false,
        ]);

        $this->assertTrue($this->service->checkIsHoliday('2026-01-03'), 'Original day');
        $this->assertTrue($this->service->checkIsHoliday('2026-01-05'), 'Comp Monday');
        $this->assertFalse($this->service->checkIsHoliday('2026-01-06'), 'Tue is not a holiday');
    }

    public function test_recurring_holiday_resolves_year_for_compensatory_check(): void
    {
        $this->enableCompensatory(true);
        // 2024-01-06 is a Saturday. Stored with is_recurring=true so the
        // service should resolve MM-DD against each requested year.
        $h = Holiday::create([
            'name' => 'Three Kings',
            'date' => '2024-01-06',
            'type' => Holiday::TYPE_PUBLIC,
            'is_recurring' => true,
        ]);

        // In 2024 Jan 6 is Sat. Comp Monday = 2024-01-08.
        $comp2024 = $this->service->getCompensatoryDay($h, 2024);
        $this->assertNotNull($comp2024);
        $this->assertSame('2024-01-08', $comp2024->toDateString());

        // In 2026 Jan 6 is Tue. No comp.
        $comp2026 = $this->service->getCompensatoryDay($h, 2026);
        $this->assertNull($comp2026);
    }
}
