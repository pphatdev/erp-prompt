<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Services;

use App\Models\Tenant\Asset;
use App\Tenants\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * Acquisition + capitalization. Auto-generates a zero-padded asset code
     * (e.g. AST-00042) using the tenant's configured prefix and the next
     * sequence for that prefix. The serial number, if supplied, is enforced
     * unique per tenant (partial unique index — see migration 73).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['asset_code'])) {
                $data['asset_code'] = $this->nextAssetCode();
            }

            $asset = Asset::create($data);

            // Generate the tenant-scoped QR verification URL once the asset id
            // exists. The frontend route is /assets/verify/{id} resolved through
            // the tenant subdomain.
            $asset->qr_code_url = $this->buildQrUrl($asset);
            $asset->saveQuietly();

            return $asset->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Asset $asset, array $data): Asset
    {
        // Re-generating the code on update would invalidate every printed QR
        // sticker in the field — block.
        unset($data['asset_code'], $data['qr_code_url'], $data['accumulated_depreciation']);

        $asset->update($data);

        return $asset->refresh();
    }

    /**
     * Soft-archive (retire) an asset without disposal journal posting. Disposal
     * gain/loss accounting goes through DisposalService::dispose().
     */
    public function archive(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            if ($asset->status !== 'retired') {
                $asset->update(['status' => 'retired']);
            }
            $asset->delete();
        });
    }

    /**
     * Allocate the next zero-padded sequential code for the tenant's active
     * prefix. Looks at the max existing **purely-numeric** suffix matching the
     * prefix and increments.
     *
     * Pulls all `LIKE prefix%` codes into PHP and filters to digit-only
     * suffixes before taking the max. Lexicographic sort in SQL was the
     * original approach but failed the moment a non-numeric code shared the
     * prefix (e.g. `AST-DEMO-012` from the demo seeder sorts above
     * `AST-00100`, then `ctype_digit('DEMO-012')` is false and the generator
     * collapses to seq=1 forever, colliding with the existing `AST-00001`).
     * Filtering in PHP also sidesteps Postgres-specific regex escaping for
     * user-configurable prefixes from settings.
     *
     * Safe under the (asset_code, tenant_id) unique constraint plus the
     * surrounding DB::transaction in create().
     */
    public function nextAssetCode(): string
    {
        // Flush the in-memory cache so the prefix read here is guaranteed to
        // be the live DB value — defensive against the case where an earlier
        // call in the same request (e.g. branding lookup during auth) has
        // already populated the SettingService cache, then the user changed
        // the prefix via /settings, then we land here using the stale cache.
        $this->settings->flushCache();

        $prefix = (string) ($this->settings->get('numbering.asset_code_prefix') ?? 'AST-');
        // Empty string would degenerate the LIKE to `%` and match every code
        // in the tenant; refuse silently and fall back to the canonical default.
        if ($prefix === '') {
            $prefix = 'AST-';
        }

        $maxSequence = Asset::query()
            ->withTrashed()
            ->where('asset_code', 'like', $prefix . '%')
            ->pluck('asset_code')
            ->map(function (string $code) use ($prefix) {
                $suffix = Str::after($code, $prefix);
                return ctype_digit($suffix) && $suffix !== '' ? (int) $suffix : null;
            })
            ->filter()
            ->max();

        $nextSequence = ((int) ($maxSequence ?? 0)) + 1;

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Build the tenant-scoped QR verification URL using the active handle as
     * a subdomain. Falls back to the central APP_URL when the system domain
     * setting is not configured — never returns a central-only path.
     */
    public function buildQrUrl(Asset $asset): string
    {
        $handle = tenant()?->getTenantKey() ?? 'tenant';
        $systemDomain = (string) ($this->settings->get('platform.system_domain')
            ?? config('platform.system_domain')
            ?? parse_url((string) config('app.url'), PHP_URL_HOST)
            ?? 'localhost');

        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$handle}.{$systemDomain}/assets/verify/{$asset->id}";
    }
}
