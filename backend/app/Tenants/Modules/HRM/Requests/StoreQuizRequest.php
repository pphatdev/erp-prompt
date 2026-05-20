<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'              => 'required|string|max:160',
            'description'        => 'nullable|string',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'pass_score'         => 'nullable|numeric|min:0|max:100',
            'status'             => 'nullable|in:draft,published,archived',

            'questions'                       => 'sometimes|array',
            'questions.*.prompt'              => 'required|string|max:5000',
            'questions.*.question_type'       => 'required|in:single_choice,multiple_choice,short_text',
            'questions.*.options'             => 'nullable|array',
            'questions.*.options.*.key'       => 'required_with:questions.*.options|string|max:20',
            'questions.*.options.*.text'      => 'required_with:questions.*.options|string|max:500',
            'questions.*.correct_answer'      => 'nullable',
            'questions.*.points'              => 'nullable|integer|min:0|max:100',
            'questions.*.sequence'            => 'nullable|integer|min:0',
        ];
    }
}
