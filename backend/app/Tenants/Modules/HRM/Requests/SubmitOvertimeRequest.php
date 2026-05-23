<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|uuid|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            // Capped at 16h per day — sanity guard; payroll rules typically
            // disallow more than ~12h OT but we let admins set the real ceiling.
            'hours' => 'required|numeric|min:0.25|max:16',
            'rate_multiplier' => 'sometimes|numeric|in:1.5,2.0,3.0',
            'reason' => 'nullable|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Self-service callers (`hrm.overtime.write.self`) MUST submit for
        // themselves; force the employee_id rather than trusting the body.
        $user = $this->user();
        $isAdmin = $user?->hasPermission('hrm.overtime.write');
        if (!$isAdmin && $user?->employee) {
            $this->merge(['employee_id' => $user->employee->id]);
        }
    }
}
