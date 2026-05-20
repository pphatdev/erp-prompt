<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.recruitment.write') ?? false;
    }

    /**
     * Editable surface of an Application — everything except `job_vacancy_id`
     * (locked to preserve funnel attribution) and `status` (moved via the
     * dedicated transition endpoint). All fields are optional so the client
     * can PATCH partial diffs without sending the whole record.
     */
    public function rules(): array
    {
        return [
            'applicant_name'        => 'sometimes|string|max:160',
            'applicant_email'       => 'sometimes|email|max:160',
            'applicant_phone'       => 'nullable|string|max:30',
            'location'              => 'nullable|string|max:160',
            'linkedin_url'          => 'nullable|url|max:255',
            'resume_path'           => 'nullable|string|max:255',
            'cover_letter'          => 'nullable|string|max:5000',
            'work_experience'       => 'nullable|array',
            'education'             => 'nullable|array',
            'skills'                => 'nullable|array',
            'expected_salary'       => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:2000',
            'referrer_employee_id'  => 'nullable|uuid|exists:employees,id',
        ];
    }
}
