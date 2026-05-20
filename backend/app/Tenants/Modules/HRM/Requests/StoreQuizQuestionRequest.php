<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt'              => 'required|string|max:5000',
            'question_type'       => 'required|in:single_choice,multiple_choice,short_text',
            'options'             => 'nullable|array',
            'options.*.key'       => 'required_with:options|string|max:20',
            'options.*.text'      => 'required_with:options|string|max:500',
            'correct_answer'      => 'nullable',
            'points'              => 'nullable|integer|min:0|max:100',
            'sequence'            => 'nullable|integer|min:0',
        ];
    }
}
