# Configuration & Tenant Settings Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in `iam.md`. Access to the configuration module is highly restricted.

### Permission Keys:
- **Module**: `configuration`
- **Actions**: `read`, `write`

### Feature Matrix — Admin Scope:
| Feature | Read | Write | Notes |
|---------|------|-------|-------|
| `profile` | `configuration.profile.read` | `configuration.profile.write` | Company details and localization |
| `branding` | `configuration.branding.read` | `configuration.branding.write` | Logos and colors |
| `modules` | `configuration.modules.read` | `configuration.modules.write` | Toggle ERP modules |
| `security` | `configuration.security.read` | `configuration.security.write` | Password policies, MFA rules |

*Note: There is no `delete` or `export` action for core configuration.*

## 2. Implementation Standards

### Storage Strategy

This module is implemented as a **key/value store** in the tenant database, not a typed `CompanyProfile` model. One row per setting, scoped by tenant.

- **Table**: `tenant_settings` (created by tenant migration `2024_01_01_000033_create_tenant_settings_table.php`).
  - `id` (uuid)
  - `key` (string, dotted convention — `branding.primary_color`, `locale.timezone`, etc.)
  - `value` (jsonb — holds string / boolean / integer / null / array)
  - `group` (string — auto-derived from `Str::before($key, '.')`; one of `branding|locale|notifications|security|general`)
  - `type` (string — `string|json|boolean|integer|color|url`; advisory hint for the frontend renderer)
  - `label`, `description` (nullable)
  - `is_public` (bool — exposes the row via `/settings/public` without auth; only set for branding rows the login screen needs)
  - `tenant_id`, timestamps, soft deletes
  - **Unique**: `(tenant_id, key)`. Index `(tenant_id, group)`.

- **Dotted key convention (mandatory)**: every key MUST be `group.name[.qualifier]`. The first segment IS the `group` column. `UpdateSettingsRequest` enforces the regex `/^[a-z0-9_]+(\.[a-z0-9_]+)+$/` — anything else is rejected at the FormRequest layer.

- **Module code lives in `app/Tenants/Modules/Settings/`** following the standard structure (`Controllers/`, `Services/`, `Resources/`, `Requests/`). Model is `App\Models\Tenant\Setting`.

- **Authoritative reads go through `SettingService`** — not `Setting::query()` — so the request-scoped in-memory cache stays warm. `SettingService::all()` materialises defaults on first call per tenant via `ensureDefaults()`, so a fresh tenant always returns a populated catalogue.

- **Defaults are seeded lazily, not via a Seeder**. `SettingService::defaults()` declares the catalogue and `ensureDefaults()` inserts any missing rows on the first read. This keeps `tenants:seed` lean and lets new default keys roll out without a tenant reseed.

### API Surface

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/settings/public` | none (tenant-scoped via `X-Tenant-Handle`) | Branding subset (`is_public = true`). Used by the login screen / public surfaces. |
| `GET` | `/api/v1/settings?group=branding` | `auth:api` | Full catalogue, optionally filtered by group. Materialises defaults on first call. |
| `PUT` | `/api/v1/settings` | `auth:api` + `configuration.write` (Gate via Form Request) | Bulk update. Body: `{ "settings": [{ "key": "branding.primary_color", "value": "16 185 129" }, ...] }`. Returns the full refreshed catalogue. |

`null` is a legitimate value (e.g. clearing `branding.logo_url`). `SettingService::set()` does **not** filter null out of the update payload.

### Branding & Frontend Sync

- **Color tokens are RGB triples** like `"59 130 246"` (space-separated, not hex). The frontend stores them in `--color-primary-rgb` and composes alpha via `rgb(var(--color-primary-rgb) / 0.1)`. Do not save `#3b82f6` — the customizer and the CSS pipeline both expect the triple format.
- **Frontend boot order**: `tenantStore.initializeTenant()` runs first (writes local default + applies any `localStorage.accent` override), then `tenantStore.syncBranding()` fetches `/settings/public` and applies the tenant primary. The user's local `localStorage.accent` ALWAYS wins over the tenant primary; clearing it via the Customizer's Reset button falls back to the tenant brand.
- **Customizer ↔ backend**: `CustomizerOffcanvas.setAccent()` writes to `localStorage` AND, if authenticated, fires `PUT /settings { settings: [{ key: 'branding.primary_color', value: rgb }] }` as a best-effort save. UI does not block on the network call — local change applies immediately.
- **Logo uploads (P0)**: when implemented, files MUST use `tenant_path()` and be served via signed URLs. Allowed MIME via `fileinfo`: `image/png`, `image/jpeg`, `image/svg+xml`, `image/webp`. Reject `.exe/.php` server-side regardless of client validation. The current implementation stores only a URL (`branding.logo_url`); when an upload endpoint is added, file metadata goes into the `attachments` table per [`rules/uploads/skill.md`](../../rules/uploads/skill.md).

### Module Toggles (planned, not yet shipped)

- Key: `modules.{slug}.enabled` (boolean). Examples: `modules.fleet.enabled`, `modules.recruitment.enabled`.
- When implemented, both the frontend navigation AND backend API routes must enforce this restriction. Backend: `CheckModuleEnabled` middleware reading `SettingService::get('modules.{slug}.enabled', true)` and returning 404 for disabled modules. Frontend: filter `navGroups` in `layouts/default.vue` against the same flag.

### Caching

- The in-memory cache in `SettingService` is per-request (Pinia-style — fresh on every HTTP request). Cross-request Redis caching is not yet enabled. When added: key with `tenant:{id}:settings`, invalidate inside `set()`/`bulkSet()` (already calls `flushCache()`).

### UI/UX Guidelines
- **Form Layout**: Settings UI uses tabbed groups (Branding / Locale / Notifications / Security / **Numbering**) in `pages/settings/index.vue`. Add new groups by extending the `tabs` array AND `SettingService::defaults()`.
- **Immediate Feedback**: Changes to branding (colors/logos) provide a live preview within the panel before the user clicks Save. The settings page tracks a `pristine` server snapshot vs. `draft` working copy and only sends changed keys.
- **Audit Logs**: Every `Setting` row mutation logs via the `Auditable` trait (captures old/new values, actor, timestamp).

## 3. Document Numbering Prefixes

> **Full specification**: [`numbering.md`](./numbering.md) — mandatory reading before implementing any feature that auto-generates a business code.

Every feature that produces a human-readable business code (Employee ID, Candidate Code, Quotation, Order, Invoice, Subscription, Purchase Order) MUST:

1. **Read its prefix from `SettingService::get('numbering.{key}')`** — never from hardcoded constants or class properties.
2. **Guard with `empty()`**: `if (empty($prefix)) { $prefix = 'DEFAULT-'; }` — allows the setting to be `null` or `""` without breaking generation.
3. **Store the separator inside the prefix**: the value `"TT-"` includes the dash, so generators concatenate directly: `"{prefix}{sequence}"`.
4. **Never rewrite existing codes** when the prefix changes — existing records are immutable identifiers; only newly created records use the new prefix.
5. **Register the key** in `SettingService::defaults()` and expose it in the frontend Numbering tab.

### All Registered Prefix Keys

| Key | Default | Feature |
|---|---|---|
| `numbering.employee_id_prefix` | `TT-` | HRM → Employee ID |
| `numbering.candidate_code_prefix` | `CAN-` | HRM → Candidate Code |
| `numbering.quotation_prefix` | `QT-` | Sales → Quotation |
| `numbering.order_prefix` | `SO-` | Sales → Sales Order |
| `numbering.invoice_prefix` | `INV-` | Sales → Invoice / Subscription Invoice |
| `numbering.subscription_prefix` | `SUB-` | Sales → Subscription |
| `numbering.po_prefix` | `PO-` | Inventory → Purchase Order |
