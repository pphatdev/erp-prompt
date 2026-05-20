<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'level' => 'nullable|string|max:50',
        ];
    }
}
