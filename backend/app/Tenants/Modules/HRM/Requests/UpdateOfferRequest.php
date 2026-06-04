<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => 'sometimes|string|max:160',
            'effectiveDate'     => 'sometimes|date',
            'expiresAt'         => 'sometimes|nullable|date',
            'baseSalary'        => 'sometimes|nullable|numeric|min:0',
            'signingBonus'      => 'sometimes|nullable|numeric|min:0',
            'currency'          => 'sometimes|string|size:3',
            'probationMonths'   => 'sometimes|integer|min:0|max:120',
            'notes'             => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $map = [
            'title'           => 'title',
            'effectiveDate'   => 'effective_date',
            'expiresAt'       => 'expires_at',
            'baseSalary'      => 'base_salary',
            'signingBonus'    => 'signing_bonus',
            'currency'        => 'currency',
            'probationMonths' => 'probation_months',
            'notes'           => 'notes',
        ];
        $v = $this->validated();
        $out = [];
        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $v)) {
                $out[$snake] = $v[$camel];
            }
        }
        return $out;
    }
}
