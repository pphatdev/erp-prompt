<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;

        return [
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($departmentId)],
        ];
    }
}
