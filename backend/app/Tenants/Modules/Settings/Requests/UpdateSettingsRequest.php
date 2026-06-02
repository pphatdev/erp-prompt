<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * `numbering.*_prefix` rules:
     *   - 1 - 16 chars
     *   - ASCII letters, digits, hyphen, underscore only
     *   - No leading/trailing whitespace
     *   - May not be the empty string (would break generators)
     *
     * Enforced in `withValidator()` since per-row content rules can't be
     * expressed via the top-level rule array - the rule depends on the
     * sibling `key` field.
     */
    public const NUMBERING_PREFIX_REGEX = '/^[A-Za-z0-9_-]{1,16}$/';

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rows = $this->input('settings', []);
            if (!is_array($rows)) return;

            foreach ($rows as $i => $row) {
                $key = $row['key'] ?? null;
                if (!is_string($key)) continue;

                if (str_starts_with($key, 'numbering.') && str_ends_with($key, '_prefix')) {
                    $this->validateNumberingPrefix($v, $i, $row['value'] ?? null);
                }
            }
        });
    }

    private function validateNumberingPrefix(Validator $v, int $index, mixed $value): void
    {
        $field = "settings.{$index}.value";

        if ($value === null || $value === '') {
            $v->errors()->add($field, 'Numbering prefix cannot be empty - the generator needs a value.');
            return;
        }
        if (!is_string($value)) {
            $v->errors()->add($field, 'Numbering prefix must be a string.');
            return;
        }
        if ($value !== trim($value)) {
            $v->errors()->add($field, 'Numbering prefix cannot have leading or trailing whitespace.');
            return;
        }
        if (!preg_match(self::NUMBERING_PREFIX_REGEX, $value)) {
            $v->errors()->add(
                $field,
                'Numbering prefix must be 1-16 characters: ASCII letters, digits, hyphen, or underscore only.'
            );
        }
    }
}
