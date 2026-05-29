<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Listeners;

use App\Models\Tenant\EmployeeAppointment;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEmployeeAppointmentFromApproval
{
    public function __construct(private readonly RecruitmentService $recruitment)
    {
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

                $result = $this->recruitment->convertToEmployee(
                    $appointment->application,
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

                $appointment->update([
                    'employee_id'  => $result['employee']->id,
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
