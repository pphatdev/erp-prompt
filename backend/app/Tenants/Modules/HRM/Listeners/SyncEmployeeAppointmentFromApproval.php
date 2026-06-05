<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Listeners;

use App\Models\Tenant\EmployeeAppointment;
use App\Models\Tenant\Offer;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\HRM\Services\OnboardingService;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.5 — this listener is the single conversion gate.
 *
 * When HR's Employee Appointment request is approved through eApprovals
 * the listener (a) materialises the Employee row, (b) links the accepted
 * Offer (if any), (c) seeds the default onboarding checklist, and
 * (d) advances the Application from `hired` → `onboarding`. Offer
 * acceptance no longer triggers any of these — see
 * {@see \App\Tenants\Modules\HRM\Services\OfferService::markAccepted()}.
 */
class SyncEmployeeAppointmentFromApproval
{
    public function __construct(
        private readonly RecruitmentService $recruitment,
        private readonly OnboardingService $onboarding,
        private readonly WorkflowStatusService $statuses,
    ) {
    }

    public function handle(ApprovalRequestFinalized $event): void
    {
        $request = $event->request;

        if ($request->requestable_type !== EmployeeAppointment::class) {
            return;
        }

        $appointment = $request->requestable;
        if (!$appointment instanceof EmployeeAppointment) {
            return;
        }

        if (!in_array($event->finalStatus, ['approved', 'rejected'], true)) {
            return;
        }

        try {
            if ($event->finalStatus === 'rejected') {
                $appointment->update([
                    'status'       => EmployeeAppointment::STATUS_REJECTED,
                    'processed_at' => now(),
                ]);
                return;
            }

            DB::transaction(function () use ($appointment) {
                $appointment->loadMissing('application');
                $application = $appointment->application;

                $result = $this->recruitment->convertToEmployee(
                    $application,
                    [
                        'first_name'      => $appointment->first_name,
                        'last_name'       => $appointment->last_name,
                        'phone'           => $appointment->phone,
                        'hired_at'        => optional($appointment->start_date)->toDateString(),
                        'base_salary'     => $appointment->base_salary,
                        'department_id'   => $appointment->department_id,
                        'position_id'     => $appointment->position_id,
                        'manager_id'      => $appointment->manager_id,
                        'employment_type' => $appointment->employment_type,
                    ],
                );

                $employee = $result['employee'];

                // Link the accepted Offer (if any) to the new Employee and
                // seed the onboarding checklist. Appointments raised without
                // a prior offer (admin / backfill path) simply skip this
                // block — the recruiter can attach a checklist manually.
                $offer = $application?->offers()
                    ->where('status', Offer::STATUS_ACCEPTED)
                    ->first();
                if ($offer) {
                    $offer->update(['employee_id' => $employee->id]);
                    $this->onboarding->seedDefaultChecklist($offer->fresh());
                }

                // Advance the Application to the terminal-success status.
                if ($application && $application->status === 'hired') {
                    $this->statuses->validateTransition('hrm.application', 'hired', 'onboarding');
                    $application->update(['status' => 'onboarding']);
                }

                $appointment->update([
                    'employee_id'  => $employee->id,
                    'status'       => EmployeeAppointment::STATUS_APPROVED,
                    'processed_at' => now(),
                ]);
            });
        } catch (DomainException $e) {
            // Listener runs after the ApprovalRequest is already updated, so a
            // domain failure here is logged rather than thrown — surfacing it
            // would 500 the approval-action HTTP response after the decision
            // is recorded.
            Log::warning('Employee appointment sync after approval failed', [
                'appointment_id'      => $appointment->id,
                'approval_request_id' => $request->id,
                'final_status'        => $event->finalStatus,
                'error'               => $e->getMessage(),
            ]);
        }
    }
}
