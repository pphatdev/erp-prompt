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
            // Decimal so 0.5 (half-day) round-trips. The service overwrites
            // `days` to exactly 0.5 when `leave_session` is morning/afternoon
            // — caller's value is only honoured for full_day requests.
            'days'          => 'nullable|numeric|min:0.5|max:365',
            'leave_session' => 'sometimes|in:full_day,morning,afternoon',
            'reason'        => 'nullable|string|max:500',
        ];
    }
}
