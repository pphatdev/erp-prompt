<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // Policy gate at controller (`AttendanceLogPolicy::clock`).
    }

    public function rules(): array
    {
        return [
            // Lat/lon are validated as optional here so a department without a
            // configured geofence can clock in without GPS. The service raises
            // 422 if the department *does* require coordinates.
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',
            // Admins can backdate a manual clock-in for an employee via the
            // write permission path (e.g. patching after a forgotten swipe).
            'clock_time' => 'nullable|date',
        ];
    }
}
