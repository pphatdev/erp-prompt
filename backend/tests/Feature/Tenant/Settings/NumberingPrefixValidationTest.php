<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Settings;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Tests\Feature\TenantTestCase;

/**
 * UpdateSettingsRequest content rules for numbering.*_prefix.
 *
 * Asserts the FormRequest rejects:
 *   - empty / null / non-string values,
 *   - leading or trailing whitespace,
 *   - values longer than 16 chars,
 *   - values containing characters outside [A-Za-z0-9_-].
 *
 * Non-numbering keys are untouched by the new rules.
 */
class NumberingPrefixValidationTest extends TenantTestCase
{
    // `$admin` is already declared (untyped) on TenantTestCase; redeclaring it
    // with a type triggers a PHP fatal under inheritance rules. Reuse the
    // inherited slot.

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure settings.write permission exists and is on the admin role.
        $perm = Permission::firstOrCreate(['slug' => 'settings.write'], [
            'name' => 'Write Settings',
            'module' => 'settings', 'feature' => 'settings', 'action' => 'write',
        ]);
        Role::where('slug', 'admin')->each(fn (Role $r) => $r->permissions()->syncWithoutDetaching([$perm->id]));

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'numbering-admin@test.com', 'password' => 'secret123',
        ]);
        $this->admin->roles()->syncWithoutDetaching([
            Role::where('slug', 'admin')->first()->id,
        ]);
    }

    // Helper renamed from `put()` to avoid clashing with the inherited public
    // TestCase::put() method (declaring a `private put()` triggers a PHP fatal
    // under inheritance rules).
    private function putSettings(array $payload)
    {
        return $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->putJson('/api/v1/settings', $payload);
    }

    public function test_valid_prefix_passes(): void
    {
        $response = $this->putSettings(['settings' => [[
            'key' => 'numbering.employee_id_prefix',
            'value' => 'ACME-',
        ]]]);

        $response->assertStatus(200);
    }

    public function test_empty_string_prefix_is_rejected(): void
    {
        $response = $this->putSettings(['settings' => [[
            'key' => 'numbering.employee_id_prefix',
            'value' => '',
        ]]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_whitespace_padded_prefix_is_rejected(): void
    {
        $response = $this->putSettings(['settings' => [[
            'key' => 'numbering.employee_id_prefix',
            'value' => ' ACME ',
        ]]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_prefix_longer_than_16_chars_is_rejected(): void
    {
        $response = $this->putSettings(['settings' => [[
            'key' => 'numbering.employee_id_prefix',
            'value' => 'ACME-DIVISION-X-Y', // 17 chars
        ]]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_prefix_with_invalid_characters_is_rejected(): void
    {
        $bad = ['AC$ME-', 'ACME!', 'AC ME-', 'ACME/', 'ACME*'];

        foreach ($bad as $value) {
            $response = $this->putSettings(['settings' => [[
                'key' => 'numbering.employee_id_prefix',
                'value' => $value,
            ]]]);
            $response->assertStatus(422, "value '{$value}' must be rejected.");
            $response->assertJsonValidationErrors(['settings.0.value']);
        }
    }

    public function test_non_numbering_keys_are_not_constrained_by_prefix_rules(): void
    {
        // Branding values are arbitrary jsonb - they should not be blocked
        // by the numbering prefix regex even if they contain spaces / special chars.
        $response = $this->putSettings(['settings' => [[
            'key' => 'branding.primary_color',
            'value' => '59 130 246',
        ]]]);

        $response->assertStatus(200);
    }

    public function test_multiple_rows_are_validated_independently(): void
    {
        $response = $this->putSettings(['settings' => [
            ['key' => 'numbering.employee_id_prefix', 'value' => 'OK-'],
            ['key' => 'numbering.invoice_prefix', 'value' => ' BAD '],
            ['key' => 'branding.theme_mode', 'value' => 'dark'],
        ]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['settings.1.value']);
        $response->assertJsonMissingValidationErrors(['settings.0.value', 'settings.2.value']);
    }
}
