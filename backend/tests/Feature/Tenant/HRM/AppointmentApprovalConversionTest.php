<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Application;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeAppointment;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Offer;
use App\Models\Tenant\OnboardingChecklist;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\HRM\Services\OfferService;
use Illuminate\Support\Facades\Log;
use Tests\Feature\TenantTestCase;

/**
 * Phase 8.5 — Appointment-request approval is the single conversion gate.
 *
 * Locks the listener {@see \App\Tenants\Modules\HRM\Listeners\SyncEmployeeAppointmentFromApproval}:
 *   - Approved → convertToEmployee + link offers.employee_id + seed onboarding
 *     checklist + advance application from `hired` to `onboarding`.
 *   - Rejected → flip appointment to `rejected`, leave the application at
 *     `hired`, do NOT create an Employee.
 *
 * Mirrors the offer-side test (OfferLifecycleTest) which asserts the
 * acceptance hand-off no longer touches the Employee row.
 */
class AppointmentApprovalConversionTest extends TenantTestCase
{
    private Application $application;
    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $vacancy = JobVacancy::create([
            'title'     => 'Senior Engineer',
            'status'    => 'open',
            'posted_at' => now(),
        ]);
        $this->application = Application::create([
            'job_vacancy_id'  => $vacancy->id,
            'applicant_name'  => 'Robin Hale',
            'applicant_email' => 'robin.hale@example.com',
            'expected_salary' => 7200.00,
            'status'          => 'offer',
            'applied_at'      => now()->subWeeks(2),
        ]);

        // Walk the candidate through draft → sent → accepted so the
        // application is at `hired` with an accepted Offer on file.
        $offers = app(OfferService::class);
        $this->offer = $offers->createOffer($this->application, [
            'title'          => 'Senior Engineer - Berlin',
            'effective_date' => '2026-07-01',
            'base_salary'    => 7200.00,
        ]);
        $this->offer = $offers->sendOffer($this->offer);
        $this->offer = $offers->markAccepted($this->offer);

        $this->assertSame('hired', $this->application->fresh()->status,
            'Setup precondition: application should be at hired after offer accept.');
    }

    public function test_approval_converts_application_seeds_checklist_and_advances_to_onboarding(): void
    {
        $appointment = $this->buildAppointment();
        $request = $this->buildApprovalRequest($appointment);

        event(new ApprovalRequestFinalized($request, 'approved'));

        // Employee created with the data captured on the appointment row.
        $employee = Employee::where('email', $this->application->applicant_email)->first();
        $this->assertNotNull($employee, 'Approval must materialise the Employee record.');
        $this->assertSame('Robin', $employee->first_name);
        $this->assertSame('active', $employee->status);

        // Application advanced to the terminal-success status.
        $this->assertSame('onboarding', $this->application->fresh()->status);
        $this->assertSame($employee->id, $this->application->fresh()->employee_id);

        // Accepted offer linked to the new employee.
        $this->assertSame($employee->id, $this->offer->fresh()->employee_id);

        // Default checklist seeded.
        $checklist = OnboardingChecklist::where('offer_id', $this->offer->id)->first();
        $this->assertNotNull($checklist);
        $this->assertSame(11, $checklist->total_tasks);
        $this->assertSame(0, $checklist->completed_tasks);

        // Appointment row stamped.
        $this->assertSame(EmployeeAppointment::STATUS_APPROVED, $appointment->fresh()->status);
        $this->assertSame($employee->id, $appointment->fresh()->employee_id);
    }

    public function test_rejection_leaves_application_at_hired_and_creates_no_employee(): void
    {
        $appointment = $this->buildAppointment();
        $request = $this->buildApprovalRequest($appointment);

        event(new ApprovalRequestFinalized($request, 'rejected'));

        $this->assertSame(EmployeeAppointment::STATUS_REJECTED, $appointment->fresh()->status);
        $this->assertSame('hired', $this->application->fresh()->status);
        $this->assertNull($this->application->fresh()->employee_id);
        $this->assertSame(0, Employee::where('email', $this->application->applicant_email)->count());
        $this->assertSame(0, OnboardingChecklist::where('offer_id', $this->offer->id)->count());
    }

    public function test_listener_swallows_conversion_exceptions(): void
    {
        // Force a downstream DomainException by reverting the application
        // to a non-hired status before approval fires. RecruitmentService
        // guards on `status === 'hired'` so this is the cleanest way to
        // exercise the catch path without mocking.
        $this->application->refresh()->update(['status' => 'offer']);
        $appointment = $this->buildAppointment();
        $request = $this->buildApprovalRequest($appointment);

        Log::spy();

        // The listener must not propagate the failure — the approval HTTP
        // response has already returned by the time we get here.
        event(new ApprovalRequestFinalized($request, 'approved'));

        Log::shouldHaveReceived('warning')->atLeast()->once();
        $this->assertSame(0, Employee::where('email', $this->application->applicant_email)->count());
        $this->assertSame(EmployeeAppointment::STATUS_PENDING, $appointment->fresh()->status,
            'A failed conversion must leave the appointment untouched so HR can retry.');
    }

    private function buildAppointment(): EmployeeAppointment
    {
        return EmployeeAppointment::create([
            'application_id'  => $this->application->id,
            'submitted_by'    => $this->admin->id,
            'first_name'      => 'Robin',
            'last_name'       => 'Hale',
            'email'           => $this->application->applicant_email,
            'phone'           => null,
            'start_date'      => '2026-07-01',
            'base_salary'     => 7200.00,
            'employment_type' => 'full_time',
            'status'          => EmployeeAppointment::STATUS_PENDING,
        ]);
    }

    private function buildApprovalRequest(EmployeeAppointment $appointment): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::firstOrCreate(
            ['module' => 'hrm', 'type' => 'employee_appointment'],
            ['name' => 'Test: HR Employee Appointment'],
        );

        return ApprovalRequest::create([
            'workflow_id'      => $workflow->id,
            'requester_id'     => $this->admin->id,
            'current_level_id' => null,
            'requestable_type' => EmployeeAppointment::class,
            'requestable_id'   => $appointment->id,
            'status'           => 'pending',
        ]);
    }
}
