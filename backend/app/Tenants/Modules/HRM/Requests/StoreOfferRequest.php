<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller-level policy gates this
    }

    public function rules(): array
    {
        return [
            'applicationId'     => 'required|uuid|exists:applications,id',
            'title'             => 'required|string|max:160',
            'effectiveDate'     => 'required|date',
            'expiresAt'         => 'nullable|date|after_or_equal:effectiveDate',
            'baseSalary'        => 'nullable|numeric|min:0',
            'signingBonus'      => 'nullable|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'probationMonths'   => 'nullable|integer|min:0|max:120',
            'notes'             => 'nullable|string|max:2000',
        ];
    }

    /**
     * Translate the camelCase JSON into the snake_case model payload the
     * service layer expects. Kept here so the controller can pass through
     * verbatim.
     */
    public function toModelPayload(): array
    {
        $v = $this->validated();
        return [
            'title'            => $v['title'],
            'effective_date'   => $v['effectiveDate'],
            'expires_at'       => $v['expiresAt'] ?? null,
            'base_salary'      => $v['baseSalary'] ?? null,
            'signing_bonus'    => $v['signingBonus'] ?? null,
            'currency'         => $v['currency'] ?? 'USD',
            'probation_months' => $v['probationMonths'] ?? null,
            'notes'            => $v['notes'] ?? null,
        ];
    }
}
