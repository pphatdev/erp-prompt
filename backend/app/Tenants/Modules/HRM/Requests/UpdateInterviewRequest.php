<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'sometimes|string|max:160',
            'round'            => 'nullable|string|max:40',
            'scheduled_at'     => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'mode'             => 'nullable|in:onsite,video,phone',
            'location'         => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:2000',
        ];
    }
}
