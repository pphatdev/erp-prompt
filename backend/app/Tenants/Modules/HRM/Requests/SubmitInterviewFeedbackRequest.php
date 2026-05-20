<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInterviewFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interviewer_id' => 'required|uuid|exists:employees,id',
            'rating'         => 'nullable|numeric|min:0|max:5',
            'strengths'      => 'nullable|string|max:5000',
            'concerns'       => 'nullable|string|max:5000',
            'recommendation' => 'nullable|in:strong_hire,hire,no_hire,strong_no_hire',
        ];
    }
}
