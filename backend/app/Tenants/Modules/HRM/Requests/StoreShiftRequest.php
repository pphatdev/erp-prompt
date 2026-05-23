<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller-level policy gate is the source of truth.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'grace_period_minutes' => 'sometimes|integer|min:0|max:240',
            'half_day_threshold_minutes' => 'nullable|integer|min:0|max:480',
        ];
    }
}
