<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        // Uniqueness is enforced per-tenant in the DB (composite indexes from
        // migration 000062). Scope validation the same way so a row from
        // another tenant doesn't block this create.
        $tenantId = tenant()?->getTenantKey();
        $scoped = fn ($q) => $q
            ->when($tenantId, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->whereNull('deleted_at');

        return [
            'employee_id'    => ['nullable', 'string', 'max:50',
                Rule::unique('employees', 'employee_id')->where($scoped)],
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => ['required', 'email',
                Rule::unique('employees', 'email')->where($scoped)],
            'phone'          => 'nullable|string|max:30',
            'gender'         => 'nullable|in:male,female,other',
            'hired_at'       => 'nullable|date',
            'base_salary'         => 'nullable|numeric|min:0',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_name'   => 'nullable|string|max:120',
            'bank_account_number' => 'nullable|string|max:50',
            'status'              => 'nullable|in:active,on_leave,terminated',
            'user_id'             => 'nullable|uuid|exists:users,id',
            'department_id'       => 'nullable|uuid|exists:departments,id',
            'position_id'         => 'nullable|uuid|exists:positions,id',
        ];
    }
}
