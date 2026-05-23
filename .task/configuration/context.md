# Configuration Module — Implementation Context

## Storage shape
- Table: `tenant_settings` (tenant DB)
- Columns: `id (uuid)`, `key (string, unique per tenant)`, `value (jsonb)`, `group (string)`, `type (string)`, `label`, `description`, `is_public (bool)`, `tenant_id`, timestamps, deleted_at
- `key` follows dotted convention: `{group}.{name}`. `group` auto-derived by model boot hook.

## Files
- Migration: `backend/database/migrations/tenant/2024_01_01_000033_create_tenant_settings_table.php`
- Model: `backend/app/Models/Tenant/Setting.php`
- Module: `backend/app/Tenants/Modules/Settings/`
  - `Controllers/SettingController.php`
  - `Services/SettingService.php`
  - `Requests/UpdateSettingsRequest.php`
  - `Resources/SettingResource.php`
- Frontend composable: `frontend/composables/useSettings.ts`
- Frontend page: `frontend/pages/settings.vue`
- Routes: `backend/routes/tenant.php` (`/settings/public` public, `/settings` GET+PUT under auth:api)

## Default catalogue (lazy-seeded by `SettingService::ensureDefaults()` on first read)
- `branding.primary_color` — RGB triple string, default `"59 130 246"`, **is_public=true**
- `branding.logo_url` — string|null, **is_public=true**
- `branding.theme_mode` — `light|dark|system`, **is_public=true**
- `locale.timezone`, `locale.language`, `locale.date_format`, `locale.currency`
- `notifications.email_enabled` (bool), `notifications.from_address`
- `security.session_timeout_minutes` (int), `security.password_min_length` (int)

## Frontend boot sequence
1. `tenantStore.initializeTenant()` — sets local tenant, applies CSS `--color-primary-rgb` from `localStorage.accent` || tenant default || `DEFAULT_PRIMARY`.
2. `tenantStore.syncBranding()` — best-effort GET `/settings/public`, applies `branding.primary_color` & `branding.logo_url`. **`localStorage.accent` still wins** over the fetched tenant primary.
3. `CustomizerOffcanvas` mount — re-applies `localStorage.accent` to CSS variable so saved customizations survive the tenant init.

## Customizer ↔ backend sync
`setAccent(rgb)`:
1. Updates ref + CSS variable + localStorage immediately (no network wait).
2. If `authStore.isAuthenticated`, fires `PUT /api/v1/settings { settings: [{ key: 'branding.primary_color', value: rgb }] }` in the background. Failure does not roll back the local change.
3. Reset removes `localStorage.accent` and the inline `--color-primary-rgb`, falling back to tenant primary via `:root` defaults.
