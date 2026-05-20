<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.leave.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'annual_allowance' => 'required|integer|min:0|max:365',
        ];
    }
}
