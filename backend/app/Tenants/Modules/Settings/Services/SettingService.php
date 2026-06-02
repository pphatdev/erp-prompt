<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Services;

use App\Models\Tenant\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Authoritative read/write for per-tenant settings.
 *
 * Caches the full set in-memory per request so multiple `get()` calls in a
 * single controller action don't reissue the same query.
 */
class SettingService
{
    /** @var Collection<int, Setting>|null */
    private ?Collection $cache = null;

    /**
     * Default catalogue applied on first read for a tenant. Mirrors the
     * frontend customizer so a fresh tenant has sensible values.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            // Branding
            [
                'key' => 'branding.primary_color',
                'group' => 'branding',
                'type' => 'color',
                'label' => 'Primary accent color',
                'is_public' => true,
                'value' => '59 130 246'
            ],
            [
                'key' => 'branding.logo_url',
                'group' => 'branding',
                'type' => 'url',
                'label' => 'Logo URL',
                'is_public' => true,
                'value' => null
            ],
            [
                'key' => 'branding.theme_mode',
                'group' => 'branding',
                'type' => 'string',
                'label' => 'Default theme mode (light|dark|system)',
                'is_public' => true,
                'value' => 'light'
            ],

            // Locale
            [
                'key' => 'locale.timezone',
                'group' => 'locale',
                'type' => 'string',
                'label' => 'Timezone',
                'value' => 'UTC'
            ],
            [
                'key' => 'locale.language',
                'group' => 'locale',
                'type' => 'string',
                'label' => 'Default language',
                'value' => 'en'
            ],
            [
                'key' => 'locale.date_format',
                'group' => 'locale',
                'type' => 'string',
                'label' => 'Date display format',
                'value' => 'YYYY-MM-DD'
            ],
            [
                'key' => 'locale.currency',
                'group' => 'locale',
                'type' => 'string',
                'label' => 'Default currency',
                'value' => 'USD'
            ],

            // Notifications
            [
                'key' => 'notifications.email_enabled',
                'group' => 'notifications',
                'type' => 'boolean',
                'label' => 'Send transactional emails',
                'value' => true
            ],
            [
                'key' => 'notifications.from_address',
                'group' => 'notifications',
                'type' => 'string',
                'label' => 'From email address',
                'value' => null
            ],

            // Security
            [
                'key' => 'security.session_timeout_minutes',
                'group' => 'security',
                'type' => 'integer',
                'label' => 'Idle session timeout (minutes)',
                'value' => 120
            ],
            [
                'key' => 'security.password_min_length',
                'group' => 'security',
                'type' => 'integer',
                'label' => 'Minimum password length',
                'value' => 8
            ],

            // Platform (read-only mirror of APP_SYSTEM_DOMAIN — cannot be changed
            // per-tenant; editing requires updating the central .env and restarting).
            [
                'key' => 'platform.system_domain',
                'group' => 'platform',
                'type' => 'string',
                'label' => 'System domain',
                'is_public' => true,
                'value' => config('platform.system_domain', 'localhost')
            ],

            // Numbering — tenant-configurable code prefixes for business docs.
            // Stored with separator included (e.g. "TT-") so the generator can
            // concatenate directly: "{prefix}{rest}".
            [
                'key' => 'numbering.employee_id_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Employee ID prefix',
                'value' => 'TT-'
            ],
            [
                'key' => 'numbering.candidate_code_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Candidate code prefix',
                'value' => 'CAN-'
            ],
            [
                'key' => 'numbering.quotation_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Quotation number prefix',
                'value' => 'QT-'
            ],
            [
                'key' => 'numbering.order_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Sales order number prefix',
                'value' => 'SO-'
            ],
            [
                'key' => 'numbering.invoice_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Invoice number prefix',
                'value' => 'INV-'
            ],
            [
                'key' => 'numbering.subscription_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Subscription number prefix',
                'value' => 'SUB-'
            ],
            [
                'key' => 'numbering.po_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Purchase order number prefix',
                'value' => 'PO-'
            ],
            [
                'key' => 'numbering.asset_code_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Fixed asset code prefix',
                'value' => 'AST-'
            ],
            [
                'key' => 'numbering.ecommerce_order_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Ecommerce order number prefix',
                'value' => 'ECOO-'
            ],
            [
                'key' => 'numbering.ecommerce_refund_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'Ecommerce refund number prefix',
                'value' => 'ECOR-'
            ],
            [
                'key' => 'numbering.pos_order_prefix',
                'group' => 'numbering',
                'type' => 'string',
                'label' => 'POS order number prefix',
                'value' => 'POS-'
            ],

            // POS - default GL account codes for tender postings. Terminal-level
            // `petty_cash_account_id` (when set) overrides the cash account.
            [
                'key' => 'pos.cash_account_code',
                'group' => 'pos',
                'type' => 'string',
                'label' => 'POS default cash drawer account code',
                'value' => '1100'
            ],
            [
                'key' => 'pos.card_account_code',
                'group' => 'pos',
                'type' => 'string',
                'label' => 'POS card-acquirer holding account code',
                'value' => '1110'
            ],
            [
                'key' => 'pos.wallet_account_code',
                'group' => 'pos',
                'type' => 'string',
                'label' => 'POS digital wallet holding account code',
                'value' => '1120'
            ],
            [
                'key' => 'pos.cash_over_short_account_code',
                'group' => 'pos',
                'type' => 'string',
                'label' => 'POS cash over/short account code',
                'value' => '5900'
            ],

            // Calendar - drives HolidayService::getCompensatoryDay. When true,
            // a holiday on Saturday/Sunday spawns a virtual Monday compensatory
            // entry and the attendance reconciler applies holiday status to it.
            [
                'key' => 'calendar.compensatory_day',
                'group' => 'calendar',
                'type' => 'boolean',
                'label' => 'Mint a Monday compensatory holiday when a holiday falls on a weekend',
                'value' => true
            ],
            [
                'key' => 'calendar.default_overtime_multiplier',
                'group' => 'calendar',
                'type' => 'string',
                'label' => 'Default holiday overtime multiplier (used when a holiday row has none set)',
                'value' => '3.00'
            ],

            // Ecommerce — account codes for the cash receipt journal posted by
            // CheckoutService::confirm and the gateway-fee expense line.
            [
                'key' => 'ecommerce.cash_account_code',
                'group' => 'ecommerce',
                'type' => 'string',
                'label' => 'Cash / gateway holding account code',
                'value' => '1100'
            ],
            [
                'key' => 'ecommerce.gateway_fee_account_code',
                'group' => 'ecommerce',
                'type' => 'string',
                'label' => 'Payment gateway fee expense account code',
                'value' => '6900'
            ],

            // FMS — toggles the Credit Note path on refund approval. When true,
            // RefundService::approve issues a CreditNote (DR Sales Returns / CR AR)
            // instead of reversing the original AR journal. Leave false until a
            // 'Sales Returns' account exists in the Chart of Accounts.
            [
                'key' => 'fms.credit_notes_enabled',
                'group' => 'fms',
                'type' => 'boolean',
                'label' => 'Issue Credit Notes on ecom refund approve',
                'value' => false
            ],
            [
                'key' => 'fms.sales_returns_account_code',
                'group' => 'fms',
                'type' => 'string',
                'label' => 'Sales returns / contra-revenue account code',
                'value' => '4900'
            ],
        ];
    }

    /**
     * Idempotent — inserts any default rows missing for the current tenant.
     * Called by `all()` so the first read for a tenant materialises defaults.
     */
    public function ensureDefaults(): void
    {
        $existing = Setting::query()->pluck('key')->all();
        $missing = collect(self::defaults())->reject(
            fn($row) => in_array($row['key'], $existing, true)
        );

        if ($missing->isEmpty()) {
            return;
        }

        // Tenant model uses `handle` as its key — `tenant('id')` returns null.
        $tenantId = tenant()?->getTenantKey();
        if (!$tenantId) {
            return;
        }

        $rows = $missing->map(fn($row) => [
            'id' => (string) Str::uuid(),
            'key' => $row['key'],
            'value' => json_encode($row['value']),
            'group' => $row['group'],
            'type' => $row['type'],
            'label' => $row['label'] ?? null,
            'description' => $row['description'] ?? null,
            'is_public' => (bool) ($row['is_public'] ?? false),
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('tenant_settings')->insert($rows);
        $this->cache = null;
    }

    /** @return Collection<int, Setting> */
    public function all(?string $group = null): Collection
    {
        if ($this->cache === null) {
            $this->ensureDefaults();
            $this->cache = Setting::query()->orderBy('group')->orderBy('key')->get();
        }

        return $group
            ? $this->cache->where('group', $group)->values()
            : $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()->firstWhere('key', $key);

        return $row ? $row->value : $default;
    }

    public function set(string $key, mixed $value, ?string $type = null): Setting
    {
        $group = Str::before($key, '.') ?: 'general';

        // Always overwrite `value` — null is a legitimate "cleared" value (e.g.
        // unset logo URL). Only carry `type` forward if the caller specified
        // one so existing rows don't get downgraded to a generic default.
        $attrs = ['value' => $value, 'group' => $group];
        if ($type !== null) {
            $attrs['type'] = $type;
        }

        $setting = Setting::query()->updateOrCreate(['key' => $key], $attrs);

        $this->cache = null;

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $pairs  key => value
     */
    public function bulkSet(array $pairs): Collection
    {
        $results = collect();
        DB::transaction(function () use ($pairs, &$results) {
            foreach ($pairs as $key => $value) {
                $results->push($this->set((string) $key, $value));
            }
        });
        $this->cache = null;

        return $results;
    }

    public function flushCache(): void
    {
        $this->cache = null;
    }
}
