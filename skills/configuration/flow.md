# Configuration & Tenant Settings Flow

## Read path (full catalogue — authenticated admin UI)

```mermaid
graph TD
    A[Tenant Administrator]
    UI[pages/settings.vue]
    Composable[useSettings.list]
    API[GET /api/v1/settings]
    Ctrl[SettingController@index]
    Svc[SettingService.all]
    Ensure[ensureDefaults — first call only]
    DB[(tenant_settings)]
    Res[SettingResource collection]

    A -->|Opens Settings page| UI
    UI --> Composable
    Composable -->|X-Tenant-Handle + Bearer| API
    API --> Ctrl
    Ctrl --> Svc
    Svc -->|cache miss| Ensure
    Ensure -->|inserts missing default rows| DB
    Svc -->|hydrate cache| DB
    Svc --> Res
    Res -->|JSON envelope| UI
```

## Write path (bulk update)

```mermaid
graph TD
    A[Tenant Administrator]
    UI[pages/settings.vue draft]
    Composable[useSettings.update]
    API[PUT /api/v1/settings]
    Req[UpdateSettingsRequest — dotted-key regex]
    Ctrl[SettingController@update]
    Svc[SettingService.bulkSet]
    Tx[(DB::transaction)]
    DB[(tenant_settings — updateOrCreate per key)]
    Audit[(audit_logs — Auditable trait)]
    Apply[Frontend re-applies CSS var if branding.primary_color changed]

    A -->|Save changes| UI
    UI -->|diff vs pristine| Composable
    Composable -->|settings: of key,value pairs| API
    API --> Req
    Req -->|422 on bad shape| UI
    Req --> Ctrl
    Ctrl --> Svc
    Svc --> Tx
    Tx --> DB
    DB -.-> Audit
    Svc -->|flushCache + return refreshed catalogue| Ctrl
    Ctrl --> Apply
```

## Public branding (no auth — login screen)

```mermaid
graph TD
    Boot[app.vue onMounted]
    InitTenant[tenantStore.initializeTenant — localStorage + DEFAULT]
    SyncBrand[tenantStore.syncBranding]
    API[GET /api/v1/settings/public]
    Ctrl[SettingController@public]
    Svc[SettingService.all filter is_public=true]
    DB[(tenant_settings WHERE is_public=true)]
    Apply[Apply branding.primary_color and logo_url]
    Override[localStorage.accent override wins]

    Boot --> InitTenant
    Boot --> SyncBrand
    SyncBrand -->|X-Tenant-Handle only — no Bearer| API
    API --> Ctrl
    Ctrl --> Svc
    Svc --> DB
    DB --> Svc
    Svc -->|public rows| Apply
    Override --> Apply
    Apply -->|--color-primary-rgb on documentElement| Boot
```

## Key conventions

- **Dotted keys**: `{group}.{name}` — first segment auto-fills the `group` column (e.g. `branding.primary_color` → `branding`). Enforced by `UpdateSettingsRequest`.
- **RGB-triple color tokens**: `"59 130 246"`, not hex. Consumed by `rgb(var(--color-primary-rgb))` in `assets/css/main.css`.
- **`is_public`**: opt-in. Set only on rows the login screen / public careers surface must read pre-auth.
- **Defaults are lazy**: `SettingService::defaults()` is the source of truth for what a new tenant starts with. Adding a new key here means it materialises on the next `GET /settings` per tenant — no seeder/migration churn needed.
