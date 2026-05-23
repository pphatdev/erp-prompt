<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array|min:1',
            // Dotted key convention: "branding.primary_color"
            'settings.*.key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)+$/'],
            // value is intentionally untyped — the model column is jsonb.
            // Callers are responsible for sending the right shape per setting.
            'settings.*.value' => 'nullable',
        ];
    }
}
