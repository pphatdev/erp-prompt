<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'employee_id'    => ['sometimes', 'string', 'max:50', Rule::unique('employees', 'employee_id')->ignore($employeeId)],
            'first_name'     => 'sometimes|string|max:100',
            'last_name'      => 'sometimes|string|max:100',
            'email'          => ['sometimes', 'email', Rule::unique('employees', 'email')->ignore($employeeId)],
            'phone'          => 'nullable|string|max:30',
            'hired_at'       => 'nullable|date',
            'base_salary'         => 'nullable|numeric|min:0',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_name'   => 'nullable|string|max:120',
            'bank_account_number' => 'nullable|string|max:50',
            'status'              => 'nullable|in:active,on_leave,terminated',
            'user_id'             => 'nullable|uuid|exists:users,id',
            'department_id'       => 'nullable|uuid|exists:departments,id',
            'position_id'         => 'nullable|uuid|exists:positions,id',
        ];
    }
}
