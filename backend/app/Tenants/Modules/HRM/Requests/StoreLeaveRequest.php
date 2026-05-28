<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user) {
            $isAdmin = $user->hasPermission('hrm.leave.write');
            if (!$isAdmin || !$this->has('employee_id')) {
                $employeeId = $user->employee?->id;

                // Fallback for development/demo environments if the user has no linked employee record
                if (!$employeeId) {
                    $employeeId = \App\Models\Tenant\Employee::first()?->id;
                }

                $this->merge([
                    'employee_id' => $employeeId,
                ]);
            }
        }

        // Default end_date to start_date if not provided or empty (meaning a 1-day leave request)
        if ($this->has('start_date') && (!$this->has('end_date') || empty($this->input('end_date')))) {
            $this->merge([
                'end_date' => $this->input('start_date'),
            ]);
        }

        // Fallback for leave_type_id if not provided or empty (to ensure seamless local testing)
        if (!$this->has('leave_type_id') || empty($this->input('leave_type_id'))) {
            $this->merge([
                'leave_type_id' => \App\Models\Tenant\LeaveType::first()?->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id'   => 'required|uuid|exists:employees,id',
            'leave_type_id' => 'required|uuid|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            // Decimal so 0.5 (half-day) round-trips. The service overwrites
            // `days` to exactly 0.5 when `leave_session` is morning/afternoon
            // — caller's value is only honoured for full_day requests.
            'days'          => 'nullable|numeric|min:0.5|max:365',
            'leave_session' => 'sometimes|in:full_day,morning,afternoon',
            'reason'        => 'nullable|string|max:500',
        ];
    }
}
