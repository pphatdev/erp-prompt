<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeLeaveAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.leave_allocation.write') ?? true;
    }

    public function rules(): array
    {
        return [
            // The only mutable counter is `allocated_days` — used/pending
            // are owned by LeaveService transitions. Admins adjust the
            // top-line allowance (carryover bonus, severance) and the
            // note is captured for audit.
            'allocated_days' => 'required|numeric|min:0|max:999.99',
            'note'           => 'nullable|string|max:500',
        ];
    }
}
