<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\Opportunity;
use App\Tenants\Modules\Crm\Services\CrmAppointmentService;
use Carbon\Carbon;
use Tests\Feature\TenantTestCase;

class CrmAppointmentLifecycleTest extends TenantTestCase
{
    private CrmAppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrmAppointmentService::class);
    }

    public function test_schedule_creates_a_scheduled_appointment(): void
    {
        $opp = $this->makeOpportunity();

        $appt = $this->service->schedule([
            'subject'        => 'Discovery call',
            'starts_at'      => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at'        => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'location'       => 'Zoom',
            'attendees'      => [['name' => 'Anna', 'email' => 'anna@acme.test']],
            'opportunity_id' => $opp->id,
        ]);

        $this->assertSame(CrmAppointment::STATUS_SCHEDULED, $appt->status);
        $this->assertSame($opp->id, $appt->opportunity_id);
        $this->assertCount(1, $appt->attendees);
    }

    public function test_schedule_rejects_inverted_time_range(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ends_at must be after starts_at');

        $this->service->schedule([
            'subject'   => 'Bad call',
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at'   => now()->toIso8601String(),
        ]);
    }

    public function test_complete_flips_status_and_sets_completed_at(): void
    {
        $appt = $this->scheduleOne();
        $done = $this->service->complete($appt);

        $this->assertSame(CrmAppointment::STATUS_COMPLETED, $done->status);
        $this->assertNotNull($done->completed_at);
    }

    public function test_cancel_records_reason_and_blocks_re_cancel_path_safely(): void
    {
        $appt = $this->scheduleOne();
        $cancelled = $this->service->cancel($appt, 'Reschedule conflict');

        $this->assertSame(CrmAppointment::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Reschedule conflict', $cancelled->cancel_reason);

        // Idempotent re-cancel returns the same row without throwing.
        $again = $this->service->cancel($cancelled);
        $this->assertSame(CrmAppointment::STATUS_CANCELLED, $again->status);
    }

    public function test_terminal_appointment_cannot_be_completed_again(): void
    {
        $appt = $this->service->complete($this->scheduleOne());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already completed');
        $this->service->complete($appt->fresh());
    }

    public function test_terminal_appointment_cannot_be_rescheduled(): void
    {
        $appt = $this->service->markNoShow($this->scheduleOne());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already no_show');
        $this->service->reschedule($appt->fresh(), [
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at'   => now()->addDays(2)->addHour()->toIso8601String(),
        ]);
    }

    public function test_list_in_window_returns_only_overlapping_appointments(): void
    {
        // Before the window
        $this->scheduleOne(start: now()->subWeek());
        // Inside the window
        $inside = $this->scheduleOne(start: now()->addDay());
        // After the window
        $this->scheduleOne(start: now()->addWeeks(2));

        $window = $this->service->listInWindow(
            Carbon::now()->startOfDay(),
            Carbon::now()->addDays(3)->endOfDay(),
        );

        $this->assertCount(1, $window);
        $this->assertSame($inside->id, $window->first()->id);
    }

    public function test_reschedule_updates_time_range(): void
    {
        $appt = $this->scheduleOne();
        $newStart = now()->addDays(2)->setTime(14, 0);
        $newEnd   = now()->addDays(2)->setTime(15, 0);

        $updated = $this->service->reschedule($appt, [
            'starts_at' => $newStart->toIso8601String(),
            'ends_at'   => $newEnd->toIso8601String(),
        ]);

        $this->assertTrue($updated->starts_at->equalTo($newStart));
        $this->assertTrue($updated->ends_at->equalTo($newEnd));
    }

    // ── helpers ──────────────────────────────────────────────────────────
    private function makeOpportunity(): Opportunity
    {
        return Opportunity::create([
            'title' => 'Discovery deal',
            'stage' => Opportunity::STAGE_QUALIFIED,
        ]);
    }

    private function scheduleOne(?Carbon $start = null): CrmAppointment
    {
        $start = $start ?: now()->addDay();
        return $this->service->schedule([
            'subject'   => 'Follow-up',
            'starts_at' => $start->toIso8601String(),
            'ends_at'   => $start->copy()->addHour()->toIso8601String(),
        ]);
    }
}
