<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\Settings\Requests\UpdateSettingsRequest;
use App\Tenants\Modules\Settings\Resources\SettingResource;
use App\Tenants\Modules\Settings\Services\SettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * GET /api/v1/settings?group=branding
     *
     * Returns the full setting catalogue for the tenant (or one group).
     * Materialises defaults on first call for the tenant.
     *
     * Gated by `settings.read` — self-service employees never see raw
     * tenant configuration; they receive pre-computed values via the
     * feature-specific payloads (payslips, leave balances, ...).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()?->hasPermission('settings.read')) {
            throw new AuthorizationException('You do not have permission to read settings.');
        }

        $group = $request->query('group');
        $rows = $this->settings->all(is_string($group) ? $group : null);

        return response()->json([
            'data' => SettingResource::collection($rows)->toArray($request),
        ]);
    }

    /**
     * PUT /api/v1/settings
     *
     * Body: { "settings": [ { "key": "branding.primary_color", "value": "16 185 129" }, ... ] }
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $pairs = collect($request->validated('settings'))
            ->mapWithKeys(fn (array $row) => [$row['key'] => $row['value'] ?? null])
            ->all();

        $this->settings->bulkSet($pairs);

        return response()->json([
            'data' => SettingResource::collection($this->settings->all())->toArray($request),
        ]);
    }

    /**
     * GET /api/v1/settings/public
     *
     * Subset flagged is_public=true. Useful for login/branding screens that
     * need logo + primary color before the user authenticates.
     */
    public function public(Request $request): JsonResponse
    {
        $rows = $this->settings->all()->filter(fn ($s) => (bool) $s->is_public)->values();

        return response()->json([
            'data' => SettingResource::collection($rows)->toArray($request),
        ]);
    }
}
