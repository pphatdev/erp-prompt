<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Calendar;

use App\Models\Tenant\CalendarEvent;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Tenants\Modules\Calendar\Resources\CalendarEventResource;
use App\Tenants\Modules\Calendar\Services\CalendarEventService;
use Illuminate\Http\Request;
use Tests\Feature\TenantTestCase;

/**
 * P0 - getCombinedEvents projects leaves; CalendarEventResource hides leave
 * titles + descriptions unless the actor holds `hrm.leave.read` or owns the
 * leave. Also covers the 90-day range guard.
 */
class CalendarPrivacyMaskingTest extends TenantTestCase
{
    private CalendarEventService $service;
    private Employee $alice;
    private Employee $bob;
    private LeaveType $vacation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CalendarEventService::class);

        $this->alice = Employee::create([
            'first_name' => 'Alice',
            'last_name' => 'Anders',
            'email' => 'alice@test.com',
            'employee_code' => 'TT-A001',
            'hire_date' => '2024-01-01',
        ]);
        $this->bob = Employee::create([
            'first_name' => 'Bob',
            'last_name' => 'Brown',
            'email' => 'bob@test.com',
            'employee_code' => 'TT-A002',
            'hire_date' => '2024-01-01',
        ]);

        $this->vacation = LeaveType::firstOrCreate(
            ['name' => 'Sick Leave'],
            ['code' => 'SL', 'days_per_year' => 10, 'is_active' => true]
        );

        Leave::create([
            'employee_id' => $this->bob->id,
            'leave_type_id' => $this->vacation->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-12',
            'status' => 'approved',
            'reason' => 'Family medical emergency',
        ]);
    }

    private function makeUser(array $permSlugs, ?string $employeeId = null): User
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => 'tester-' . uniqid() . '@test.com',
            'password' => 'secret123',
        ]);
        if ($employeeId) {
            // Link by writing the user_id on the employee (Employee belongsTo User
            // pattern in this codebase).
            \DB::table('employees')->where('id', $employeeId)->update(['user_id' => $user->id]);
        }
        // Create / attach a synthetic role carrying the requested slugs.
        $role = Role::create([
            'name' => 'TestRole-' . uniqid(),
            'slug' => 'test-role-' . uniqid(),
            'description' => 'Synthetic role for masking tests',
        ]);
        $ids = [];
        foreach ($permSlugs as $slug) {
            $row = Permission::firstOrCreate(['slug' => $slug], [
                'name' => 'Test ' . $slug,
                'module' => 'calendar',
                'feature' => 'event',
                'action' => 'read',
            ]);
            $ids[] = $row->id;
        }
        $role->permissions()->syncWithoutDetaching($ids);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user->refresh();
    }

    public function test_combined_events_includes_leave_projection(): void
    {
        $envelope = $this->service->getCombinedEvents('2026-02-01', '2026-02-28');

        $leaveRows = array_values(array_filter(
            $envelope['events'],
            fn ($r) => $r['source'] === CalendarEventService::SOURCE_LEAVE
        ));
        $this->assertCount(1, $leaveRows, 'Service should project one leave row.');
        $this->assertSame($this->bob->id, $leaveRows[0]['employeeId']);
    }

    public function test_resource_masks_leave_title_when_actor_lacks_hrm_leave_read(): void
    {
        $observer = $this->makeUser(['calendar.event.read']); // no hrm.leave.read

        $envelope = $this->service->getCombinedEvents('2026-02-01', '2026-02-28');
        $leaveRow = collect($envelope['events'])->firstWhere('source', CalendarEventService::SOURCE_LEAVE);

        $request = Request::create('/calendar/events', 'GET');
        $request->setUserResolver(fn () => $observer);

        $arr = (new CalendarEventResource($leaveRow))->toArray($request);

        $this->assertSame('Leave - Confirmed', $arr['title'],
            'Without hrm.leave.read, leave title must mask.');
        $this->assertNull($arr['description'], 'Description must hide.');
        $this->assertNull($arr['employeeId'], 'Owner id must hide.');
    }

    public function test_resource_shows_full_leave_detail_when_actor_holds_hrm_leave_read(): void
    {
        $observer = $this->makeUser(['hrm.leave.read', 'calendar.event.read']);

        $envelope = $this->service->getCombinedEvents('2026-02-01', '2026-02-28');
        $leaveRow = collect($envelope['events'])->firstWhere('source', CalendarEventService::SOURCE_LEAVE);

        $request = Request::create('/calendar/events', 'GET');
        $request->setUserResolver(fn () => $observer);

        $arr = (new CalendarEventResource($leaveRow))->toArray($request);

        $this->assertSame('Sick Leave', $arr['title']);
        $this->assertSame('Family medical emergency', $arr['description']);
        $this->assertSame($this->bob->id, $arr['employeeId']);
    }

    public function test_resource_exposes_own_leave_to_owner_without_hrm_leave_read(): void
    {
        // Make a user, link them to Bob, and grant only `.read.self`.
        $bobUser = $this->makeUser(['calendar.event.read.self'], $this->bob->id);

        $envelope = $this->service->getCombinedEvents('2026-02-01', '2026-02-28');
        $leaveRow = collect($envelope['events'])->firstWhere('source', CalendarEventService::SOURCE_LEAVE);

        $request = Request::create('/calendar/events', 'GET');
        $request->setUserResolver(fn () => $bobUser);

        $arr = (new CalendarEventResource($leaveRow))->toArray($request);

        // Owner always sees their own leave detail even without hrm.leave.read.
        $this->assertSame('Sick Leave', $arr['title']);
        $this->assertSame('Family medical emergency', $arr['description']);
        $this->assertSame($this->bob->id, $arr['employeeId']);
    }

    public function test_resource_masks_personal_calendar_event_to_other_actors(): void
    {
        $owner = $this->bob;
        $event = CalendarEvent::create([
            'title' => 'Bob: dentist appointment',
            'start_time' => '2026-02-15 09:00:00',
            'end_time' => '2026-02-15 10:00:00',
            'category' => CalendarEvent::CATEGORY_PERSONAL,
            'employee_id' => $owner->id,
            'is_all_day' => false,
        ]);

        $envelope = $this->service->getCombinedEvents('2026-02-14', '2026-02-16');
        $row = collect($envelope['events'])
            ->firstWhere(fn ($r) => $r['source'] === CalendarEventService::SOURCE_CALENDAR && $r['id'] === $event->id);

        // Other user, no admin read perm.
        $other = $this->makeUser(['calendar.event.read.self']);

        $request = Request::create('/calendar/events', 'GET');
        $request->setUserResolver(fn () => $other);

        $arr = (new CalendarEventResource($row))->toArray($request);
        $this->assertSame('Personal event', $arr['title'],
            'Personal events must mask to non-owners without calendar.event.read.');
        $this->assertNull($arr['description']);
    }

    public function test_combined_events_rejects_inverted_range(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('start must be on or before the end date');
        $this->service->getCombinedEvents('2026-02-10', '2026-02-01');
    }

    public function test_combined_events_rejects_range_exceeding_max(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot exceed');
        // 100 days > 90 day max.
        $this->service->getCombinedEvents('2026-01-01', '2026-04-11');
    }
}
