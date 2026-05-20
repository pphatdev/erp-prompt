<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                 => 'required|string|max:160',
            'description'           => 'nullable|string',
            'location'              => 'nullable|string|max:120',
            'employment_type'       => 'nullable|in:full_time,part_time,contract,intern',
            'experience_min_years'  => 'nullable|integer|min:0|max:60',
            'experience_max_years'  => 'nullable|integer|min:0|max:60|gte:experience_min_years',
            'salary_min'            => 'nullable|numeric|min:0',
            'salary_max'            => 'nullable|numeric|min:0|gte:salary_min',
            'vacancies_count'       => 'nullable|integer|min:1',
            'status'                => 'nullable|in:draft,open,paused,closed,filled',
            'posted_at'             => 'nullable|date',
            'closes_at'             => 'nullable|date|after_or_equal:posted_at',
            'department_id'         => 'nullable|uuid|exists:departments,id',
            'position_id'           => 'nullable|uuid|exists:positions,id',
        ];
    }
}
