<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Application;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Offer;
use App\Models\Tenant\OnboardingChecklist;
use App\Models\Tenant\OnboardingTask;
use App\Tenants\Modules\HRM\Services\ESignatureService;
use App\Tenants\Modules\HRM\Services\OfferService;
use App\Tenants\Modules\HRM\Services\OnboardingService;
use DomainException;
use Tests\Feature\TenantTestCase;

/**
 * Phase 8 + Phase 8.5 — Offer lifecycle.
 *
 * Phase 8.5 split the side-effects of `markAccepted` so the HR appointment
 * approval stays the single conversion gate. These tests lock the split:
 *   - createOffer requires application.status === 'offer'.
 *   - markAccepted only flips offer.status + advances application to 'hired'.
 *   - It does NOT create an Employee row or seed an onboarding checklist.
 *
 * The conversion + checklist seeding path is covered separately in
 * AppointmentApprovalConversionTest.
 */
class OfferLifecycleTest extends TenantTestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $vacancy = JobVacancy::create([
            'title'      => 'Senior Engineer',
            'status'     => 'open',
            'posted_at'  => now(),
        ]);
        $this->application = Application::create([
            'job_vacancy_id'  => $vacancy->id,
            'applicant_name'  => 'Avery Smith',
            'applicant_email' => 'avery.smith@example.com',
            'applicant_phone' => null,
            'expected_salary' => 6500.00,
            'status'          => 'offer',
            'applied_at'      => now()->subWeeks(3),
        ]);
    }

    public function test_create_offer_stamps_reference_number_and_draft_status(): void
    {
        $offer = app(OfferService::class)->createOffer($this->application, [
            'title'          => 'Senior Engineer - Berlin',
            'effective_date' => '2026-07-01',
            'expires_at'     => '2026-06-25',
            'base_salary'    => 6500.00,
            'currency'       => 'USD',
        ]);

        $this->assertSame(Offer::STATUS_DRAFT, $offer->status);
        $this->assertNotNull($offer->reference_number);
        $this->assertStringStartsWith('OFR-', $offer->reference_number);
        $this->assertSame(3, (int) $offer->probation_months,
            'Probation defaults to hrm.recruitment.probation_period_default (3).');
    }

    public function test_create_offer_throws_when_application_is_not_at_offer_stage(): void
    {
        $this->application->update(['status' => 'interview']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Offers can only be drafted while the application is at the Job Offer stage.');

        app(OfferService::class)->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
    }

    public function test_send_offer_stamps_envelope_id_and_flips_to_sent(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer - Berlin',
            'effective_date' => '2026-07-01',
            'base_salary'    => 6500.00,
        ]);

        $offer = $offers->sendOffer($offer);

        $this->assertSame(Offer::STATUS_SENT, $offer->status);
        $this->assertNotNull($offer->esign_envelope_id);
        $this->assertSame(ESignatureService::PROVIDER_MOCK, $offer->esign_provider);
        $this->assertNotNull($offer->sent_at);
    }

    public function test_accept_offer_flips_application_to_hired_without_creating_employee(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer - Berlin',
            'effective_date' => '2026-07-01',
            'base_salary'    => 6500.00,
        ]);
        $offer = $offers->sendOffer($offer);

        $offer = $offers->markAccepted($offer);

        // Offer flipped to accepted, signed_at stamped. employee_id stays null
        // until the appointment-request approval listener links it.
        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->status);
        $this->assertNotNull($offer->signed_at);
        $this->assertNull($offer->employee_id,
            'Offer acceptance must not link an employee yet — that happens on appointment approval.');

        // Application advanced from `offer` to `hired`. Still no Employee link.
        $this->assertSame('hired', $this->application->fresh()->status);
        $this->assertNull($this->application->fresh()->employee_id);

        // No Employee row, no checklist materialised yet.
        $this->assertSame(0, Employee::where('email', $this->application->applicant_email)->count(),
            'Employee row must NOT be created on offer acceptance.');
        $this->assertSame(0, OnboardingChecklist::where('offer_id', $offer->id)->count(),
            'Onboarding checklist must NOT be seeded on offer acceptance.');
    }

    public function test_accept_offer_is_idempotent_and_does_not_double_transition(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
        $offer = $offers->sendOffer($offer);
        $first = $offers->markAccepted($offer);

        // Calling accept a second time (e.g. webhook retry) must short-circuit
        // and NOT attempt to re-transition the application or fire side-effects.
        $second = $offers->markAccepted($first->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(Offer::STATUS_ACCEPTED, $second->status);
        $this->assertSame('hired', $this->application->fresh()->status,
            'Idempotent re-accept must leave the application at hired.');
        $this->assertSame(0, OnboardingChecklist::where('offer_id', $offer->id)->count());
        $this->assertSame(0, Employee::where('email', $this->application->applicant_email)->count());
    }

    public function test_decline_offer_records_reason_without_advancing_status(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
        $offer = $offers->sendOffer($offer);

        $offer = $offers->markDeclined($offer, 'Accepting another role.');

        $this->assertSame(Offer::STATUS_DECLINED, $offer->status);
        $this->assertSame('Accepting another role.', $offer->decline_reason);
        $this->assertNull($offer->employee_id);
        $this->assertSame('offer', $this->application->fresh()->status,
            'Decline must leave the application at the offer stage (recruiter may draft a replacement).');
        $this->assertSame(0, OnboardingChecklist::where('offer_id', $offer->id)->count());
    }

    public function test_expire_stale_offers_flips_past_due_drafts_and_sent_rows(): void
    {
        $offers = app(OfferService::class);
        $stale = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2024-01-01',
            'expires_at'     => '2024-01-15',
        ]);
        // Bypass sendOffer so the test doesn't need a second application.

        $count = $offers->expireStaleOffers();

        $this->assertSame(1, $count);
        $this->assertSame(Offer::STATUS_EXPIRED, $stale->fresh()->status);
    }

    public function test_complete_onboarding_task_bumps_checklist_progress(): void
    {
        // Manually drive the offer to `accepted` and then explicitly seed the
        // checklist via OnboardingService — Phase 8.5 routes that seeding
        // through the appointment-approval listener, but this test exercises
        // the task-completion path in isolation.
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
        $offer = $offers->sendOffer($offer);
        $offer = $offers->markAccepted($offer);

        $checklist = app(OnboardingService::class)->seedDefaultChecklist($offer->fresh());

        $task = $checklist->tasks()->first();

        app(OnboardingService::class)->completeTask($task, 'Done - device imaged.');

        $checklist = $checklist->fresh();
        $this->assertSame(1, $checklist->completed_tasks);
        $this->assertSame(OnboardingChecklist::STATUS_IN_PROGRESS, $checklist->status);
        $this->assertSame(OnboardingTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame('Done - device imaged.', $task->fresh()->completion_notes);
    }
}
