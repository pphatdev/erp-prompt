<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id'  => 'required|uuid|exists:applications,id',
            'start_date'      => 'required|date',
            'base_salary'     => 'nullable|numeric|min:0',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'manager_id'      => 'nullable|uuid|exists:employees,id',
            'department_id'   => 'nullable|uuid|exists:departments,id',
            'position_id'     => 'nullable|uuid|exists:positions,id',
            'notes'           => 'nullable|string|max:2000',
        ];
    }
}
