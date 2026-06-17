<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeLeaveAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.leave_allocation.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'employee_id'    => 'required|uuid|exists:employees,id',
            'leave_type_id'  => 'required|uuid|exists:leave_types,id',
            'year'           => 'required|integer|min:2000|max:2100',
            'allocated_days' => 'required|numeric|min:0|max:999.99',
            'note'           => 'nullable|string|max:500',
        ];
    }
}
