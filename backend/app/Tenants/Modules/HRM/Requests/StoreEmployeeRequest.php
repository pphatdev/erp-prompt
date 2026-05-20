<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'employee_id'    => 'required|string|max:50|unique:employees,employee_id',
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => 'required|email|unique:employees,email',
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
