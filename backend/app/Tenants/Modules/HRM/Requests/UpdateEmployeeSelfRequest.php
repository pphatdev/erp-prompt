<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service profile update — strict whitelist of fields an employee can
 * change on their own row. Authorization is enforced by EmployeePolicy
 * (`updateSelf`); this Request stops sensitive fields (salary, bank, status,
 * department, position, email, user_id) from sneaking through even if the
 * client tries to submit them.
 */
class UpdateEmployeeSelfRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real check happens in the controller via $this->authorize('updateSelf', ...).
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'phone'      => 'nullable|string|max:30',
        ];
    }
}
