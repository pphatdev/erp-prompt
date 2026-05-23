<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|uuid|exists:employees,id',
            'start_date' => 'required|date_format:Y-m-d',
            // NULL end_date = open-ended (active going forward). The service
            // closes any previously-open assignment when this one begins.
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }
}
