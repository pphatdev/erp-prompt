# Configuration (Settings) Skill

> Tenant-configurable settings registry. Authoritative status: [`.task/configuration/task.md`](../../.task/configuration/task.md) · Numbering subtask: [`.task/numbering/task.md`](../../.task/numbering/task.md) · Detailed status: [`./overview.md`](./overview.md) · Numbering spec: [`./numbering.md`](./numbering.md)

## Scope
Key/value settings store with grouped tabs in the frontend. Branding, locale, notifications, security, document numbering (7 prefixes), modules management (admin-only), and platform-level overrides. HRM Settings ships its own 5 sub-pages under `/settings/apps/hrm/*`.

## Code map
- **Backend module:** `backend/app/Tenants/Modules/Settings/` — Controllers, Requests, Resources, Services
- **Model:** `app/Models/Tenant/Setting.php` (key/value with `'json'` cast — never `json_encode()` the value)
- **Service:** `Settings\SettingService::defaults()` registers all default keys including 7 numbering prefixes
- **Request:** `UpdateSettingsRequest` — per-key validation; `NUMBERING_PREFIX_REGEX = /^[A-Za-z0-9_-]{1,16}$/` for `numbering.*_prefix` keys
- **Helper:** `App\Support\GenerationRetry::handle()` wraps generators, retries on SQLSTATE 23505 (up to 5 attempts)
- **Frontend pages:** `frontend/pages/settings/index.vue` (tabs shell), `roles.vue`, `users.vue`, `apps/hrm/{recruitment,leave,attendance,payroll,performance}.vue`, `configuration/*`
- **Composables:** `useSettings.ts`, `useHrmSettings.ts`, `usePrefixCodes.ts`

## Routes
- `// Configuration & Tenant Settings` (~line 193–196 of `routes/tenant.php`)

## Permissions
`settings.read`, `settings.write`. Seeded by `SettingsPermissionSeeder` (also backfills existing tenants).

## Critical patterns
- `Setting.value` cast is `'json'` (not `'array'`) so scalars round-trip
- `UpdateSettingsRequest::authorize()` uses `hasPermission()` not Gate
- All numbering generators read from `SettingService` with `empty()` fallback — no hardcoded values
- Numbering prefix changes are immutable for already-issued documents (UI shows callout)

## See also
- [`./rules.md`](./rules.md) — storage contract + key conventions
- [`./flow.md`](./flow.md) — sequence diagrams
- [`./numbering.md`](./numbering.md) — 7-prefix system
- [`./testing.md`](./testing.md)
- [`skills/modules/skill.md`](../modules/skill.md) — module enablement (admin-only tab)
