# Skill: Multi-Tenant Client Management

## Context
Use this skill when developing features that require tenant isolation, managing client company onboarding, or implementing cross-tenant logic. This ensures that every client company (tenant) is strictly isolated and their data is handled securely via the Handle-Based Routing system.

## Guidelines

### 1. Tenant Identification & Routing
- **Subdomain Context**: Always assume the primary method of identification is the request subdomain (the `handle`).
- **Middleware Usage**: Ensure the `InitializeTenancyByDomain` middleware is active for all tenant-specific routes.
- **Header Injection**: When performing cross-origin requests, manually inject the `X-Tenant-Handle` header.

### 2. Working with Isolated Databases
- **Dynamic Connections**: Never hardcode database connections. Rely on the tenancy package to switch the active connection automatically based on the `handle`.
- **Landlord Access**: When data from the central database is needed (e.g., subscription checks), use the `Landlord` connection explicitly.
- **Migration Scope**: Use the `--tenants` flag when running migrations to ensure schema changes apply to all client company databases.

### 3. Tenant-Aware Features
- **Scoped Storage**: Use the `tenant_path()` helper to store files. This ensures assets are saved in `storage/tenants/{handle}/`.
- **Global Scopes**: Always apply the `BelongsToTenant` trait to models that should be scoped to a single company.
- **Cache Isolation**: Use tenant-specific cache prefixes to prevent data leakage between companies.

### 4. Branding & Personalization
- **useTenant Composable**: In the frontend, use `useTenant()` to fetch the active company's logo, colors, and enabled modules.
- **Design Tokens**: Bind CSS variables to the values returned by the tenant configuration to support white-labeling.

## Migration & Seeding — Mandatory Order

> **CRITICAL**: The migration order is strictly enforced. Deviating from this sequence causes errors like `relation "tenants" does not exist` or `relation "users" does not exist`.

Run **in this exact order** on every fresh environment:

```bash
# Step 1 — Create the central landlord tables (tenants, domains, etc.)
# The central/ sub-directory is NOT auto-discovered by php artisan migrate
php artisan migrate --path=database/migrations/central

# Step 2 — Run root-level shared migrations (oauth_*, cache, jobs, etc.)
php artisan migrate

# Step 3 — Seed the central/landlord database (this PROVISIONS tenant databases)
php artisan db:seed

# Step 4 — NOW migrate tenant databases (they physically exist after Step 3)
php artisan tenants:migrate

# Step 5 — Seed tenant-specific data (users, roles, demo data, etc.)
php artisan tenants:seed
```

### Migration Path Reference

| Step | Path / Command | Purpose |
|---|---|---|
| 1 | `migrate --path=database/migrations/central` | Creates `tenants` & `domains` tables in the landlord DB |
| 2 | `migrate` | Creates shared tables: `oauth_*`, `cache`, `jobs`, etc. |
| 3 | `db:seed` | Seeds the landlord DB and **provisions tenant databases** |
| 4 | `tenants:migrate` | Runs all `migrations/tenant/` schemas on each tenant DB |
| 5 | `tenants:seed` | Seeds tenant-scoped data (users, roles, config, etc.) |

> Adding a new tenant after initial setup? Run `php artisan tenants:migrate` and `php artisan tenants:seed` again — they target only un-migrated databases.

## Best Practices
- **Isolation First**: Always write automated tests that verify "Tenant A" cannot access "Tenant B" data.
- **Idempotent Setup**: Ensure the client onboarding process is idempotent; if provisioning fails halfway, it should be able to resume without creating duplicate databases.
- **Handle Validation**: Enforce strict regex validation for company handles (e.g., `^[a-z0-9-]+$`) to prevent URL-unfriendly subdomains.

## Troubleshooting
- **`relation "tenants" does not exist`**: The central migrations haven't been run. Execute `php artisan migrate --path=database/migrations/central` first. The `migrations/central/` subdirectory is **not** auto-discovered by the default `migrate` command.
- **`relation "users" does not exist`**: `tenants:migrate` was run before `db:seed`. Tenant databases are created during seeding — if you migrate before seeding, the databases don't exist yet. Run `php artisan db:seed` then `php artisan tenants:migrate`.
- **Missing Database**: If a "Database not found" error occurs, verify that the tenant's `handle` matches the database name or the mapping in the central `tenants` table.
- **Shared Data Leakage**: If data from one company appears in another, check if the `BelongsToTenant` trait is missing or if the model is using a shared cache key.
- **Routing Loop**: If subdomains are redirecting incorrectly, check the `SESSION_DOMAIN` configuration in the `.env` file to ensure cookies are shared across subdomains.
- **Tenancy Central Connection Error**: If you see `Database connection [central] not configured`, update `config/tenancy.php` to set `'central_connection' => env('DB_CONNECTION', 'pgsql')` instead of hardcoding `'central'`.
