<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quiz_id'           => 'required|uuid|exists:quizzes,id',
            'expires_in_hours'  => 'nullable|integer|min:1|max:720',
        ];
    }
}
