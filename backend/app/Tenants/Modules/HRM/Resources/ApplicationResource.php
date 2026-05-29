<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeSalary = $request->user()?->can('hrm.recruitment.read') ?? false;

        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get('numbering.candidate_code_prefix') ?: 'CAN-';
        $candidateCode = $this->candidate_code;
        if ($candidateCode && preg_match('/(\d+)$/', $candidateCode, $matches)) {
            $candidateCode = $prefix . $matches[1];
        }

        $pendingAppointment = $this->relationLoaded('pendingAppointments')
            ? $this->pendingAppointments->first()
            : null;

        return [
            'id' => $this->id,
            'candidateCode' => $candidateCode,
            'jobVacancyId' => $this->job_vacancy_id,
            'employeeId' => $this->employee_id,
            'applicantName' => $this->applicant_name,
            'applicantEmail' => $this->applicant_email,
            'applicantPhone' => $this->applicant_phone,
            'location' => $this->location,
            'linkedinUrl' => $this->linkedin_url,
            'resumePath' => $this->resume_path,
            'coverLetter' => $this->cover_letter,
            'workExperience' => $this->work_experience,
            'education' => $this->education,
            'skills' => $this->skills,
            'expectedSalary' => $canSeeSalary && $this->expected_salary !== null
                ? (float) $this->expected_salary
                : null,
            'notes' => $this->notes,
            'status' => $this->status,
            'appliedAt' => optional($this->applied_at)->toIso8601String(),
            'convertedAt' => optional($this->converted_at)->toIso8601String(),
            'pendingAppointmentRequest' => $pendingAppointment ? [
                'id'        => $pendingAppointment->id,
                'status'    => $pendingAppointment->status,
                'createdAt' => optional($pendingAppointment->created_at)->toIso8601String(),
            ] : null,
            'vacancy' => $this->whenLoaded('vacancy', fn () => $this->vacancy ? new JobVacancyResource($this->vacancy) : null),
            'referrer' => $this->whenLoaded('referrer', fn () => $this->referrer ? new EmployeeResource($this->referrer) : null),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
