<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeSalary = $request->user()?->can('hrm.recruitment.read') ?? false;

        return [
            'id'              => $this->id,
            'applicationId'   => $this->application_id,
            'candidateCode'   => $this->resolveCandidateCode(),
            'employeeId'      => $this->employee_id,
            'submittedBy'     => $this->submitted_by,
            'firstName'       => $this->first_name,
            'lastName'        => $this->last_name,
            'fullName'        => trim("{$this->first_name} {$this->last_name}"),
            'email'           => $this->email,
            'phone'           => $this->phone,
            'departmentId'    => $this->department_id,
            'positionId'      => $this->position_id,
            'managerId'       => $this->manager_id,
            'startDate'       => optional($this->start_date)->toDateString(),
            'baseSalary'      => $canSeeSalary && $this->base_salary !== null
                ? (float) $this->base_salary
                : null,
            'employmentType'  => $this->employment_type,
            'notes'           => $this->notes,
            'status'          => $this->status,
            'processedAt'     => optional($this->processed_at)->toIso8601String(),
            'department'      => $this->whenLoaded('department', fn () => $this->department ? new DepartmentResource($this->department) : null),
            'position'        => $this->whenLoaded('position', fn () => $this->position ? new PositionResource($this->position) : null),
            'manager'         => $this->whenLoaded('manager', fn () => $this->manager ? new EmployeeResource($this->manager) : null),
            'employee'        => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'application'     => $this->whenLoaded('application', fn () => $this->application ? new ApplicationResource($this->application) : null),
            'createdAt'       => optional($this->created_at)->toIso8601String(),
            'updatedAt'       => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Resolve the tenant-configured candidate code (e.g. CAN-202605-001) from
     * the underlying Application. Emitted as a top-level field so the
     * appointment payload always carries the code even when the `application`
     * relation isn't eager-loaded.
     */
    private function resolveCandidateCode(): ?string
    {
        $app = $this->relationLoaded('application') ? $this->application : null;
        if (!$app) {
            // Lazy-load only when not already in memory. Cheap on detail views;
            // index views always eager-load via the controller's morphWith.
            $app = $this->application()->first();
        }
        if (!$app || !$app->candidate_code) {
            return null;
        }

        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.candidate_code_prefix') ?: 'CAN-';

        if (preg_match('/(\d+)$/', (string) $app->candidate_code, $matches)) {
            return $prefix . $matches[1];
        }
        return $app->candidate_code;
    }
}
