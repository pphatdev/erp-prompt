<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:1000',
        ];
    }
}
