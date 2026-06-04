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

    /**
     * Account-code values for hrm.payroll.account_* settings. Mirrors the
     * convention used by the seeded defaults (EXP-WAGES, LIA-TAX, ...).
     */
    public const ACCOUNT_CODE_REGEX = '/^[A-Za-z0-9_-]{1,40}$/';

    public const LEAVE_ACCRUAL_CYCLES = ['calendar_year', 'fiscal_year', 'hire_date'];

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

            $hrmRowsByKey = [];

            foreach ($rows as $i => $row) {
                $key = $row['key'] ?? null;
                if (!is_string($key)) continue;
                $value = $row['value'] ?? null;

                if (str_starts_with($key, 'numbering.') && str_ends_with($key, '_prefix')) {
                    $this->validateNumberingPrefix($v, $i, $value);
                }

                if (str_starts_with($key, 'hrm.')) {
                    $this->validateHrmSetting($v, $i, $key, $value);
                    $hrmRowsByKey[$key] = ['index' => $i, 'value' => $value];
                }
            }

            $this->validateAppraisalWeightsPair($v, $hrmRowsByKey);
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

    /**
     * Per-key rules for hrm.* settings. Mirrors the exhaustive registry in
     * skills/hrm/rules.md §10 — each branch validates a single key in
     * isolation. Sum-of-weights cross-check lives in
     * {@see validateAppraisalWeightsPair()}.
     */
    private function validateHrmSetting(Validator $v, int $index, string $key, mixed $value): void
    {
        $field = "settings.{$index}.value";

        switch ($key) {
            // Recruitment
            case 'hrm.recruitment.probation_period_default':
                $this->assertIntInRange($v, $field, $value, 0, 120, 'Probation period must be a whole number of months between 0 and 120.');
                return;
            case 'hrm.recruitment.revert_window_days':
                $this->assertIntInRange($v, $field, $value, 0, 365, 'Revert window must be a whole number of days between 0 and 365.');
                return;
            case 'hrm.recruitment.enable_public_careers':
                $this->assertBool($v, $field, $value, 'Public careers toggle must be true or false.');
                return;

            // Leave
            case 'hrm.leave.standard_work_week':
                $this->assertWorkWeekArray($v, $field, $value);
                return;
            case 'hrm.leave.accrual_cycle_start':
                if (!is_string($value) || !in_array($value, self::LEAVE_ACCRUAL_CYCLES, true)) {
                    $v->errors()->add($field, 'Accrual cycle must be one of: ' . implode(', ', self::LEAVE_ACCRUAL_CYCLES) . '.');
                }
                return;
            case 'hrm.leave.allow_negative_balance':
                $this->assertBool($v, $field, $value, 'Allow-negative-balance must be true or false.');
                return;
            case 'hrm.leave.max_carryover_days':
                $this->assertNumericInRange($v, $field, $value, 0, 365, 'Max carryover days must be a number between 0 and 365.');
                return;
            case 'hrm.leave.min_notice_days':
                $this->assertIntInRange($v, $field, $value, 0, 365, 'Minimum notice must be a whole number of days between 0 and 365.');
                return;
            case 'hrm.leave.max_consecutive_days':
                $this->assertIntInRange($v, $field, $value, 0, 365, 'Max consecutive days must be a whole number between 0 (unlimited) and 365.');
                return;
            case 'hrm.leave.attachment_required_days':
                $this->assertIntInRange($v, $field, $value, 0, 365, 'Attachment-required threshold must be a whole number of days between 0 and 365.');
                return;
            case 'hrm.leave.auto_approve_days':
                $this->assertIntInRange($v, $field, $value, 0, 365, 'Auto-approve threshold must be a whole number of days between 0 (disabled) and 365.');
                return;

            // Attendance
            case 'hrm.attendance.enable_geofencing':
            case 'hrm.attendance.enable_ip_whitelisting':
                $this->assertBool($v, $field, $value, 'Toggle must be true or false.');
                return;
            case 'hrm.attendance.geofence_radius_meters':
                $this->assertIntInRange($v, $field, $value, 1, 100000, 'Geofence radius must be a whole number of meters between 1 and 100000.');
                return;
            case 'hrm.attendance.ip_whitelist':
                if ($value !== null && !is_string($value)) {
                    $v->errors()->add($field, 'IP whitelist must be a comma-separated string.');
                }
                return;
            case 'hrm.attendance.auto_clock_out_hours':
                $this->assertIntInRange($v, $field, $value, 1, 72, 'Auto clock-out window must be a whole number of hours between 1 and 72.');
                return;

            // Payroll
            case 'hrm.payroll.monthly_work_hours_standard':
                $this->assertIntInRange($v, $field, $value, 1, 744, 'Monthly work hours must be a whole number between 1 and 744.');
                return;
            case 'hrm.payroll.default_payday':
                $this->assertIntInRange($v, $field, $value, 1, 31, 'Default payday must be a calendar day between 1 and 31.');
                return;
            case 'hrm.payroll.fms_posting_enabled':
                $this->assertBool($v, $field, $value, 'FMS posting toggle must be true or false.');
                return;
            case 'hrm.payroll.account_wages_expense':
            case 'hrm.payroll.account_tax_payable':
            case 'hrm.payroll.account_social_security_payable':
            case 'hrm.payroll.account_wages_payable':
                $this->assertAccountCode($v, $field, $value);
                return;

            // Performance
            case 'hrm.appraisal.self_evaluation_weight':
            case 'hrm.appraisal.manager_evaluation_weight':
                $this->assertNumericInRange($v, $field, $value, 0, 100, 'Appraisal weight must be a number between 0 and 100.');
                return;
        }
    }

    /**
     * Enforce self + manager weights summing to 100 only when BOTH values
     * pass per-key validation. When only one weight is in the payload, the
     * other is sourced from the persisted setting so partial updates don't
     * spuriously fail. Skipped entirely if any single-key validation already
     * flagged an error against either field.
     *
     * @param  array<string, array{index: int, value: mixed}>  $hrmRowsByKey
     */
    private function validateAppraisalWeightsPair(Validator $v, array $hrmRowsByKey): void
    {
        $selfKey = 'hrm.appraisal.self_evaluation_weight';
        $mgrKey = 'hrm.appraisal.manager_evaluation_weight';

        if (!isset($hrmRowsByKey[$selfKey]) && !isset($hrmRowsByKey[$mgrKey])) {
            return;
        }

        $errors = $v->errors();
        $selfField = isset($hrmRowsByKey[$selfKey]) ? "settings.{$hrmRowsByKey[$selfKey]['index']}.value" : null;
        $mgrField = isset($hrmRowsByKey[$mgrKey]) ? "settings.{$hrmRowsByKey[$mgrKey]['index']}.value" : null;

        if ($selfField && $errors->has($selfField)) return;
        if ($mgrField && $errors->has($mgrField)) return;

        $self = isset($hrmRowsByKey[$selfKey])
            ? $hrmRowsByKey[$selfKey]['value']
            : app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get($selfKey);
        $mgr = isset($hrmRowsByKey[$mgrKey])
            ? $hrmRowsByKey[$mgrKey]['value']
            : app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get($mgrKey);

        if (!is_numeric($self) || !is_numeric($mgr)) return;

        if ((float) $self + (float) $mgr !== 100.0) {
            $field = $selfField ?? $mgrField;
            $v->errors()->add($field, 'Self-evaluation and manager-evaluation weights must sum to exactly 100.');
        }
    }

    private function assertBool(Validator $v, string $field, mixed $value, string $message): void
    {
        if (is_bool($value)) return;
        if ($value === 1 || $value === 0 || $value === '1' || $value === '0' || $value === 'true' || $value === 'false') return;
        $v->errors()->add($field, $message);
    }

    private function assertIntInRange(Validator $v, string $field, mixed $value, int $min, int $max, string $message): void
    {
        if (is_int($value) && $value >= $min && $value <= $max) return;
        if (is_string($value) && ctype_digit(ltrim($value, '-')) && (int) $value >= $min && (int) $value <= $max) return;
        $v->errors()->add($field, $message);
    }

    private function assertNumericInRange(Validator $v, string $field, mixed $value, float $min, float $max, string $message): void
    {
        if (!is_numeric($value)) {
            $v->errors()->add($field, $message);
            return;
        }
        $f = (float) $value;
        if ($f < $min || $f > $max) {
            $v->errors()->add($field, $message);
        }
    }

    private function assertAccountCode(Validator $v, string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $v->errors()->add($field, 'Account code cannot be empty.');
            return;
        }
        if (!is_string($value)) {
            $v->errors()->add($field, 'Account code must be a string.');
            return;
        }
        if ($value !== trim($value)) {
            $v->errors()->add($field, 'Account code cannot have leading or trailing whitespace.');
            return;
        }
        if (!preg_match(self::ACCOUNT_CODE_REGEX, $value)) {
            $v->errors()->add($field, 'Account code must be 1-40 characters: ASCII letters, digits, hyphen, or underscore only.');
        }
    }

    private function assertWorkWeekArray(Validator $v, string $field, mixed $value): void
    {
        if (!is_array($value)) {
            $v->errors()->add($field, 'Standard work week must be an array of ISO weekday numbers (1=Mon ... 7=Sun).');
            return;
        }
        if ($value === []) {
            $v->errors()->add($field, 'Standard work week must contain at least one working day.');
            return;
        }
        $seen = [];
        foreach ($value as $day) {
            if (!is_int($day) && !(is_string($day) && ctype_digit($day))) {
                $v->errors()->add($field, 'Each working day must be an integer between 1 (Mon) and 7 (Sun).');
                return;
            }
            $n = (int) $day;
            if ($n < 1 || $n > 7) {
                $v->errors()->add($field, 'Each working day must be between 1 (Mon) and 7 (Sun).');
                return;
            }
            if (isset($seen[$n])) {
                $v->errors()->add($field, 'Standard work week cannot contain duplicate days.');
                return;
            }
            $seen[$n] = true;
        }
    }
}
