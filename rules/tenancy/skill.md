# Skill: Multi-Tenant Client Management

## Context
Use this skill when developing features that require tenant isolation, managing client company onboarding, or implementing cross-tenant logic. This ensures that every client company (tenant) is strictly isolated and their data is handled securely via the Handle-Based Routing system.

## Guidelines

### 1. Tenant Identification & Routing
- **Handle-Based Identification**: The primary method of tenant identification is the `X-Tenant-Handle` request header. Subdomain-based routing is secondary.
- **Middleware Usage**: Ensure the `InitializeTenancyByHandle` middleware is active for all tenant-specific routes (registered in `routes/tenant.php`).
- **Header Injection**: All API requests from the frontend must include the `X-Tenant-Handle` header. The `useApi` composable in the frontend handles this automatically.

### 2. Working with Isolated Databases
- **Dynamic Connections**: Never hardcode database connections. Rely on the tenancy package to switch the active connection automatically based on the `handle`.
- **Landlord Access**: When data from the central database is needed (e.g., subscription checks), use the `Landlord` connection explicitly.
- **Migration Scope**: Tenant schema changes go into `database/migrations/tenant/`. Run `php artisan tenants:migrate` to apply them to all tenants.
- **Central Connection Config**: In `config/tenancy.php`, always set `'central_connection' => env('DB_CONNECTION', 'pgsql')`. Never hardcode `'central'` — that connection name does not exist in this project.

### 3. Tenant-Aware Features
- **Scoped Storage**: Use the `tenant_path()` helper to store files. This ensures assets are saved in `storage/tenants/{handle}/`.
- **Global Scopes**: Always apply the `BelongsToTenant` trait (`App\Models\Traits\BelongsToTenant`) to models that should be scoped to a single company.
- **Cache Isolation**: Use tenant-specific cache prefixes to prevent data leakage between companies (handled automatically by `CacheTenancyBootstrapper`).

### 4. Branding & Personalization
- **useTenant Composable**: In the frontend, use `useTenant()` to fetch the active company's logo, colors, and enabled modules.
- **Design Tokens**: Bind CSS variables to the values returned by the tenant configuration to support white-labeling.

---

## Migration & Seeding — Mandatory Order

> **CRITICAL**: The migration order is strictly enforced. Deviating from this sequence causes errors like `relation "tenants" does not exist` or `relation "users" does not exist`.

Run **in this exact order** on every fresh environment:

```bash
# Step 1 — Generate Passport RSA keys and create default OAuth clients (ONCE)
#           This publishes and runs the oauth_* migrations.
php artisan passport:install

# Step 2 — Create the password grant client; note the printed ID/secret → .env
php artisan passport:client --password

# Step 3 — Create the central landlord tables (tenants, domains)
#           The central/ sub-directory is NOT auto-discovered by php artisan migrate
php artisan migrate --path=database/migrations/central

# Step 4 — Run root-level shared migrations (oauth_*, cache, personal_access_tokens, etc.)
php artisan migrate

# Step 5 — Seed the central/landlord database (this PROVISIONS tenant databases)
php artisan db:seed

# Step 6 — NOW migrate tenant databases (they physically exist after Step 5)
php artisan tenants:migrate

# Step 7 — Seed tenant-specific data (users, roles, demo data, etc.)
php artisan tenants:seed

# Step 8 — Clear config cache after updating .env with passport credentials
php artisan config:clear
```

### Migration Path Reference

| Step | Path / Command | Purpose |
|---|---|---|
| 1 | `passport:install` | Generates RSA keys + creates oauth_* tables in landlord DB |
| 2 | `passport:client --password` | Creates password grant client (ID 33 by default in seeders) |
| 3 | `migrate --path=database/migrations/central` | Creates `tenants` & `domains` tables in the landlord DB |
| 4 | `migrate` | Creates shared tables: root-level `oauth_*`, `cache`, `jobs`, etc. |
| 5 | `db:seed` | Seeds the landlord DB and **provisions tenant databases** |
| 6 | `tenants:migrate` | Runs all `migrations/tenant/` schemas on each tenant DB |
| 7 | `tenants:seed` | Seeds tenant-scoped data (users, roles, config, etc.) |
| 8 | `config:clear` | Flushes config cache after `.env` changes |

> Adding a new tenant after initial setup? Run `php artisan tenants:migrate` and `php artisan tenants:seed` again — they target only un-migrated databases.

---

## Tenant OAuth Tables (Per-Tenant)

Each tenant database also contains its own set of OAuth tables (located in `database/migrations/tenant/2016_06_01_*`). These are separate from the root-level OAuth tables in the central database.

**Why both?** Passport operates within the currently active database connection. When a tenant request is active, Passport reads/writes to the **tenant's** oauth tables. The central oauth tables serve as a fallback and for central-level API clients.

**Idempotency Rule:** All tenant-level oauth migration files MUST use `Schema::hasTable()` guards:
```php
public function up(): void
{
    if (!Schema::hasTable('oauth_access_tokens')) {
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('user_id', 100)->nullable()->index(); // UUID-compatible
            // ...
        });
    }
}
```

---

## Best Practices
- **Isolation First**: Always write automated tests that verify "Tenant A" cannot access "Tenant B" data.
- **Idempotent Setup**: Ensure the client onboarding process is idempotent; if provisioning fails halfway, it should be able to resume without creating duplicate databases.
- **Handle Validation**: Enforce strict regex validation for company handles (e.g., `^[a-z0-9-]+$`) to prevent URL-unfriendly subdomains.
- **Never `DB_CONNECTION=central`**: This connection name does not exist. Always use `DB_CONNECTION=pgsql` — the tenancy package manages switching internally.

---

## Troubleshooting

| Symptom | Root Cause | Fix |
|---|---|---|
| `relation "tenants" does not exist` | Central migrations haven't been run | `php artisan migrate --path=database/migrations/central` |
| `relation "users" does not exist` on login | `tenants:migrate` was run before `db:seed` | `db:seed` → `tenants:migrate` → `tenants:seed` |
| `Database not found` | Tenant handle doesn't match provisioned DB | Verify the tenant's `handle` in the central `tenants` table |
| Shared Data Leakage between tenants | `BelongsToTenant` trait missing, or shared cache key | Add `BelongsToTenant` trait to model; check cache prefix config |
| Routing Loop on subdomains | Incorrect `SESSION_DOMAIN` config | Set `SESSION_DOMAIN` to share cookies across subdomains |
| `Database connection [central] not configured` | `config/tenancy.php` hardcodes `'central'` | Set `'central_connection' => env('DB_CONNECTION', 'pgsql')` |
| `SQLSTATE[42P07]: relation "oauth_*" already exists` on `tenants:migrate` | Tenant oauth migrations missing `Schema::hasTable()` guard | Add `if (!Schema::hasTable('...'))` wrapper to the `up()` method |
| `oauth_access_tokens.user_id` type error | Passport default used `unsignedBigInteger` but users have UUIDs | Run `tenants:migrate` — `_000027_fix_oauth_user_id_to_uuid.php` will patch it |
