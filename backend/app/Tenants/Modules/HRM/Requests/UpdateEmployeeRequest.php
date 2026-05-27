<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use App\Models\Tenant\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrm.employee.write') ?? true;
    }

    public function rules(): array
    {
        // Route param may be the bound Employee model OR the raw UUID string,
        // depending on when validation runs vs. when binding resolves.
        $bound = $this->route('employee');
        $employeeId = $bound instanceof Employee ? $bound->id : $bound;

        // Uniqueness is enforced per-tenant in the DB (composite indexes from
        // migration 000062). Match that scope here so validation never blocks
        // on a row from a different tenant.
        $tenantId = tenant()?->getTenantKey();
        $scoped = fn ($q) => $q
            ->when($tenantId, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->whereNull('deleted_at');

        return [
            'employee_id'    => ['sometimes', 'string', 'max:50',
                Rule::unique('employees', 'employee_id')->ignore($employeeId)->where($scoped)],
            'first_name'     => 'sometimes|string|max:100',
            'last_name'      => 'sometimes|string|max:100',
            'email'          => ['sometimes', 'email',
                Rule::unique('employees', 'email')->ignore($employeeId)->where($scoped)],
            'phone'          => 'nullable|string|max:30',
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
