<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Application;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\EmployeeAppointment;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeAppointmentService
{
    public function __construct(private readonly ApprovalService $approvals)
    {
    }

    /**
     * Submit an appointment request. Snapshots the candidate from the
     * application, captures HR-supplied overrides, and dispatches the
     * eApprovals ticket so the row appears under My Requests / approver
     * queues. Refuses if an active (pending) appointment already exists
     * for the same application so HR can't fan out parallel requests.
     */
    public function submit(array $data): EmployeeAppointment
    {
        /** @var Application $application */
        $application = Application::with('vacancy')->findOrFail($data['application_id']);

        if ($application->status !== 'hired') {
            throw new DomainException('Only hired candidates can be appointed.');
        }

        if ($application->employee_id) {
            throw new DomainException('This candidate is already linked to an employee.');
        }

        $existingPending = EmployeeAppointment::where('application_id', $application->id)
            ->where('status', EmployeeAppointment::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new DomainException('An appointment request is already pending for this candidate.');
        }

        $parts = preg_split('/\s+/', trim($application->applicant_name)) ?: [];
        $firstName = (string) (array_shift($parts) ?: 'Hire');
        $lastName  = implode(' ', $parts) ?: 'Hired';

        $payload = [
            'application_id'  => $application->id,
            'submitted_by'    => Auth::id(),
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'email'           => $application->applicant_email,
            'phone'           => $application->applicant_phone,
            'department_id'   => $data['department_id'] ?? $application->vacancy?->department_id,
            'position_id'     => $data['position_id']   ?? $application->vacancy?->position_id,
            'manager_id'      => $data['manager_id']    ?? null,
            'start_date'      => $data['start_date'],
            'base_salary'     => $data['base_salary']   ?? $application->expected_salary,
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'notes'           => $data['notes'] ?? null,
            'status'          => EmployeeAppointment::STATUS_PENDING,
        ];

        return DB::transaction(function () use ($payload) {
            $appointment = EmployeeAppointment::create($payload);

            $workflow = $this->appointmentWorkflow();
            $requesterId = Auth::id();

            if ($workflow && $requesterId) {
                $this->approvals->submitRequest(
                    workflowId: $workflow->id,
                    requesterId: (string) $requesterId,
                    requestableType: EmployeeAppointment::class,
                    requestableId: (string) $appointment->id,
                );
            }

            return $appointment;
        });
    }

    private function appointmentWorkflow(): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('module', 'hrm')
            ->where('type', 'employee_appointment')
            ->orderBy('created_at')
            ->first();
    }
}
