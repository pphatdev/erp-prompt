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
use Tests\Feature\TenantTestCase;

/**
 * Phase 8 - Offer + Onboarding lifecycle.
 *
 * Covers the happy path: draft -> send -> accept -> Employee + checklist.
 * Webhook-signature path is exercised separately via the HTTP route so the
 * verification + dispatch wiring is locked too.
 */
class OfferLifecycleTest extends TenantTestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $vacancy = JobVacancy::create([
            'title' => 'Senior Engineer',
            'status' => 'open',
            'posted_at' => now(),
        ]);
        $this->application = Application::create([
            'job_vacancy_id'      => $vacancy->id,
            'applicant_name'      => 'Avery Smith',
            'applicant_email'     => 'avery.smith@example.com',
            'applicant_phone'     => null,
            'expected_salary'     => 6500.00,
            'status'              => 'hired',
            'applied_at'          => now()->subWeeks(3),
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

    public function test_accept_offer_converts_application_to_employee_and_seeds_checklist(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer - Berlin',
            'effective_date' => '2026-07-01',
            'base_salary'    => 6500.00,
        ]);
        $offer = $offers->sendOffer($offer);

        $offer = $offers->markAccepted($offer);

        // Offer flipped + application linked to a freshly minted Employee.
        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->status);
        $this->assertNotNull($offer->employee_id);
        $this->assertNotNull($offer->signed_at);
        $this->assertSame($offer->employee_id, $this->application->fresh()->employee_id);
        $this->assertNotNull(Employee::find($offer->employee_id));

        // Onboarding checklist seeded with the default 11-task template.
        $checklist = OnboardingChecklist::where('offer_id', $offer->id)->first();
        $this->assertNotNull($checklist, 'Accepting an offer must seed an OnboardingChecklist.');
        $this->assertSame($offer->employee_id, $checklist->employee_id);
        $this->assertSame(11, $checklist->total_tasks);
        $this->assertSame(0, $checklist->completed_tasks);
        $this->assertSame(OnboardingChecklist::STATUS_PENDING, $checklist->status);

        // The IT-provisioning task pre-dates the start date by 3 days.
        $itTask = $checklist->tasks()->where('owner_role', 'it')
            ->where('due_offset_days', -3)->first();
        $this->assertNotNull($itTask);
        $this->assertSame('2026-06-28', $itTask->due_date->toDateString());
    }

    public function test_accept_offer_is_idempotent(): void
    {
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
        $offer = $offers->sendOffer($offer);
        $first = $offers->markAccepted($offer);

        // Calling accept a second time (e.g. webhook retry) must not
        // duplicate the Employee or the checklist.
        $second = $offers->markAccepted($first->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->employee_id, $second->employee_id);
        $this->assertSame(1, OnboardingChecklist::where('offer_id', $offer->id)->count());
        $this->assertSame(1, Employee::where('email', $this->application->applicant_email)->count());
    }

    public function test_decline_offer_records_reason_without_creating_employee(): void
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
        $this->assertNull($this->application->fresh()->employee_id,
            'Decline must not convert the application to an Employee.');
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
        $offers = app(OfferService::class);
        $offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer',
            'effective_date' => '2026-07-01',
        ]);
        $offer = $offers->sendOffer($offer);
        $offer = $offers->markAccepted($offer);

        $checklist = OnboardingChecklist::where('offer_id', $offer->id)->firstOrFail();
        $task = $checklist->tasks()->first();

        app(\App\Tenants\Modules\HRM\Services\OnboardingService::class)
            ->completeTask($task, 'Done - device imaged.');

        $checklist = $checklist->fresh();
        $this->assertSame(1, $checklist->completed_tasks);
        $this->assertSame(OnboardingChecklist::STATUS_IN_PROGRESS, $checklist->status);
        $this->assertSame(OnboardingTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame('Done - device imaged.', $task->fresh()->completion_notes);
    }
}
