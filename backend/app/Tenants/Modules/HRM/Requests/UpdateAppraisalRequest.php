<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppraisalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_id'     => 'nullable|uuid|exists:employees,id|different:employee_id',
            'cycle'           => 'sometimes|string|max:40',
            'period_start'    => 'sometimes|date',
            'period_end'      => 'sometimes|date|after_or_equal:period_start',
            'overall_rating'  => 'nullable|numeric|min:0|max:5',
            'strengths'       => 'nullable|string|max:5000',
            'improvements'    => 'nullable|string|max:5000',
            'goals'           => 'nullable|array',
            'goals.*.title'   => 'required_with:goals|string|max:200',
            'goals.*.status'  => 'nullable|in:pending,in_progress,achieved,missed',
            'goals.*.due'     => 'nullable|date',
        ];
    }
}
