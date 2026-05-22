# Configuration Testing Standards

## Backend (Pest)

### Priority 0: Tenancy & Isolation
- `GET /api/v1/settings` as Tenant A admin returns ONLY Tenant A's rows (the `BelongsToTenant` global scope filters by `tenant_id`).
- `PUT /api/v1/settings` from Tenant A admin cannot mutate Tenant B's rows — separate DB connection makes cross-write impossible; assert anyway as a regression guard.
- Regular `employee` role calling `PUT /api/v1/settings` returns 403 (the FormRequest's `authorize()` checks `configuration.write`).
- `GET /api/v1/settings/public` works WITHOUT a Bearer token but still requires `X-Tenant-Handle` (asserts the route is registered outside the `auth:api` group).

### Priority 1: Business Logic
- **Lazy defaults**: First `GET /api/v1/settings` against a fresh tenant returns the full default catalogue (every key in `SettingService::defaults()`). A second call doesn't duplicate rows — assert `Setting::count()` is stable across two reads.
- **Dotted-key validation**: `PUT /api/v1/settings` with `key: "primary_color"` (no dot) returns 422. `key: "branding.primary_color"` succeeds.
- **`null` is a valid value**: `PUT /api/v1/settings` with `{ "key": "branding.logo_url", "value": null }` persists `null` (not "missing"). The `set()` method must NOT filter null out of the update payload.
- **Group auto-derive**: Creating a setting with key `notifications.from_address` and no explicit group writes `group = 'notifications'` (the model boot hook splits on `.`).
- **Public filter**: `GET /api/v1/settings/public` returns only rows where `is_public = true` — assert that `security.session_timeout_minutes` is NOT in the response while `branding.primary_color` IS.
- **Audit Logs**: Updating `locale.timezone` from `UTC` to `Asia/Phnom_Penh` generates an `audit_logs` entry with old/new values and the admin actor.

### Priority 2: Data Integrity
- Bulk update is transactional — if one key in the payload fails (e.g. a future strict-type check), no rows are mutated. Wrap `bulkSet` in `DB::transaction()` (already done) and assert rollback via a forced failure.
- Cache flush: after `set()`, the in-memory `SettingService` cache is null, so the next `all()` call hits the DB. Assert by spying on the query count.

## Frontend (Vitest / Playwright)
- `pages/settings.vue` loads the catalogue on mount; the Save button is disabled when `draft` deep-equals `pristine`.
- Modifying `branding.primary_color` via the swatch updates `draft['branding.primary_color']` but does NOT yet write to `documentElement.style` — only Save commits the CSS variable change (matches saved-not-saved separation).
- The Customizer offcanvas (`CustomizerOffcanvas.vue`) writes immediately to `documentElement.style` + localStorage AND best-effort PUTs `/settings` when authenticated. If the PUT fails, the local accent stays applied — assert via a network mock that throws.
- `tenantStore.syncBranding()` applies `branding.primary_color` from `/settings/public` only when `localStorage.accent` is absent. With a saved accent, the local override wins.

## Regression: password double-hash (linked via [`rules/auth/skill.md`](../../rules/auth/skill.md))
Not a Configuration test, but lives nearby because the IAM flow lives in this module's neighbourhood:
- Create a user via `POST /api/v1/users` with `{ password: 'secret123' }`. Immediately call `POST /api/v1/auth/login` with the same credentials. Assert 200 + a non-empty `access_token`. This is the canonical regression test that catches `Hash::make()` being reintroduced in `UserService`.
