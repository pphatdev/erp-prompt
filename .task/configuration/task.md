# Task Context: Configuration & Tenant Settings

## Objective
Ship the key/value tenant configuration store covering branding, locale, notifications, and security defaults, with public branding exposed for the login screen and a frontend admin page to manage values.

## Checklist
- [x] `tenant_settings` migration with `(tenant_id, key)` unique + `(tenant_id, group)` index (tenant migration `_000033_`)
- [x] `App\Models\Tenant\Setting` model with `BelongsToTenant` + `Auditable` + soft deletes; auto-derive `group` from dotted key
- [x] `SettingService` with `get/set/bulkSet/all/ensureDefaults/flushCache` and per-request in-memory cache
- [x] `SettingController` with `index`, `update`, `public` actions
- [x] `UpdateSettingsRequest` validates dotted-key regex; allows `null` values
- [x] Routes: `GET /api/v1/settings/public` (no auth), `GET|PUT /api/v1/settings` (auth:api)
- [x] Frontend `composables/useSettings.ts` typed wrapper
- [x] Frontend `pages/settings.vue` with Branding / Locale / Notifications / Security tabs, dirty tracking, revert/save
- [x] Nav entry under Settings group → /settings
- [x] `tenantStore.syncBranding()` pulls public settings on boot; local `localStorage.accent` override wins
- [x] `CustomizerOffcanvas.setAccent` persists primary color to backend when authenticated
- [x] Update `skills/configuration/{overview,rules,flow,testing}.md` to match shipped implementation
- [ ] Logo upload endpoint + signed URL serving (planned — currently URL-only)
- [ ] `CheckModuleEnabled` middleware reading `modules.{slug}.enabled` (planned)
- [ ] Redis cross-request cache layer with tenant-prefixed keys (planned)
- [ ] Pest tests for the regression scenarios in `skills/configuration/testing.md` (lazy defaults, dotted-key validation, null persistence, public filter, tenancy isolation)

## Related Fixes (same session)
- [x] **Login bug for newly-created users** — removed `Hash::make()` from `UserService::createUser/updateUser` (double-hash with `'password' => 'hashed'` cast was producing `bcrypt(bcrypt($plain))`). Documented in [`rules/auth/skill.md`](../../rules/auth/skill.md), [`rules/backend/skill.md`](../../rules/backend/skill.md), and [`skills/iam/rules.md`](../../skills/iam/rules.md).
- [x] **Customizer accent override** — `CustomizerOffcanvas.onMounted` now applies saved accent (was only updating ref); `tenantStore.setTenantByHandle` respects `localStorage.accent` instead of overwriting on every tenant init.
