<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Submitting an application is a public-ish action; gated by route auth.
    }

    public function rules(): array
    {
        return [
            'job_vacancy_id'        => 'required|uuid|exists:job_vacancies,id',
            'applicant_name'        => 'required|string|max:160',
            'applicant_email'       => 'required|email|max:160',
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
            'employee_id'           => 'nullable|uuid|exists:employees,id',
        ];
    }
}
