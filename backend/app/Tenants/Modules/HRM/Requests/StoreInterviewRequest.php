<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id'    => 'required|uuid|exists:applications,id',
            'quiz_attempt_id'   => 'nullable|uuid|exists:quiz_attempts,id',
            'title'             => 'required|string|max:160',
            'round'             => 'nullable|string|max:40',
            'scheduled_at'      => 'required|date',
            'duration_minutes'  => 'nullable|integer|min:5|max:480',
            'mode'              => 'nullable|in:onsite,video,phone',
            'location'          => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:2000',
            'interviewer_ids'   => 'nullable|array',
            'interviewer_ids.*' => 'uuid|exists:employees,id',
        ];
    }
}
