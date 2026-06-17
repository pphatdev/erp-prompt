<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.leave.write') ?? true;
    }

    public function rules(): array
    {
        // For PATCH/PUT routes the route-model binding gives us the row;
        // ignore its id when re-checking the unique constraint so an
        // edit that doesn't change `code` doesn't fail validation.
        $id = $this->route('leaveType') ? (string) $this->route('leaveType')->id : null;

        return [
            'name'              => 'required|string|max:80',
            'code'              => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('leave_types', 'code')->ignore($id),
            ],
            'annual_allowance'  => 'required|integer|min:0|max:365',
            'is_paid'           => 'sometimes|boolean',
            'is_accrued'        => 'sometimes|boolean',
            'applicable_gender' => 'sometimes|in:any,male,female',
        ];
    }

    /**
     * Empty `code` becomes null so the unique-per-tenant constraint
     * accepts multiple legacy rows without codes. Truthy code is
     * upper-cased to keep the catalogue tidy (VAC vs vac).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $raw = trim((string) $this->input('code'));
            $this->merge(['code' => $raw === '' ? null : strtoupper($raw)]);
        }
    }
}
