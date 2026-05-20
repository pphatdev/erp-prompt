<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.leave.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'employee_id'   => 'required|uuid|exists:employees,id',
            'leave_type_id' => 'required|uuid|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'days'          => 'nullable|integer|min:1',
            'reason'        => 'nullable|string|max:500',
        ];
    }
}
