# Backend Complete Setup & Project Reference

> This document captures the **exact** structure, conventions, and setup sequence for the `backend/` Laravel 11 project.
> Any developer or agent onboarding on a new machine **must** follow this document from top to bottom.

---

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Runtime | PHP | `^8.2` (8.3 recommended) |
| Framework | Laravel | `^11.0` |
| Auth | Laravel Passport | `^12.0` |
| Multi-tenancy | stancl/tenancy | `^3.8` |
| Database | PostgreSQL | `15+` (port `5433` in Docker) |
| Testing | Pest PHP | `^2.34` |

---

## Directory Structure

```
backend/
├── app/
│   ├── Console/Commands/         # Custom Artisan commands
│   ├── Exceptions/               # Global exception handlers
│   ├── Http/
│   │   ├── Controllers/          # Central (landlord) controllers only
│   │   └── Middleware/           # e.g. InitializeTenancyByHandle
│   ├── Models/
│   │   ├── Central/              # Landlord models (Tenant.php)
│   │   ├── Tenant/               # (reserved — tenant models live in modules)
│   │   ├── Traits/
│   │   │   ├── Auditable.php     # Boot hooks: created/updated/deleted logging
│   │   │   └── BelongsToTenant.php  # Wraps stancl BelongsToTenant
│   │   └── Casts/
│   ├── Policies/                 # Laravel Policies for central resources
│   ├── Providers/
│   │   ├── CentralServiceProvider.php  # Landlord bootstrapping
│   │   └── TenantServiceProvider.php   # Per-tenant bootstrapping
│   ├── Services/                 # Global/central services (Auth, Logging)
│   └── Tenants/
│       └── Modules/              # All business modules live here
│           ├── IAM/              # Identity & Access Management
│           ├── HRM/              # Human Resources Management
│           ├── FMS/              # Financial Management System
│           ├── Sales/            # CRM & Sales
│           ├── Inventory/        # Inventory & Stock
│           ├── Fleet/            # Fleet Management
│           ├── Assets/           # Asset Management
│           ├── Projects/         # Project & Task Management
│           ├── Approvals/        # e-Approvals Workflow
│           ├── Documents/        # CMS Documents
│           ├── EDocuments/       # e-Documents (checkin/checkout)
│           └── Reporting/        # Dashboards & Widgets
├── bootstrap/
│   └── app.php                   # Laravel 11 bootstrap — routes registered here
├── config/
│   ├── tenancy.php               # stancl/tenancy configuration
│   ├── passport.php              # Passport OAuth2 settings
│   ├── database.php              # DB connections (pgsql primary)
│   └── auth.php                  # Guard and provider definitions
├── database/
│   ├── migrations/
│   │   ├── central/              # Landlord schema: tenants, domains tables
│   │   │   └── 2024_01_01_000001_create_tenants_table.php
│   │   ├── tenant/               # Per-tenant schemas (31 migration files)
│   │   │   ├── 2016_06_01_000001_create_oauth_auth_codes_table.php        # ⚠️ with hasTable guard
│   │   │   ├── 2016_06_01_000002_create_oauth_access_tokens_table.php     # ⚠️ with hasTable guard
│   │   │   ├── 2016_06_01_000003_create_oauth_refresh_tokens_table.php    # ⚠️ with hasTable guard
│   │   │   ├── 2016_06_01_000004_create_oauth_clients_table.php           # ⚠️ with hasTable guard
│   │   │   ├── 2016_06_01_000005_create_oauth_personal_access_clients_table.php  # ⚠️ with hasTable guard
│   │   │   ├── 2024_01_01_000002_create_users_table.php
│   │   │   ├── 2024_01_01_000003_create_rbac_tables.php
│   │   │   └── ... (all business module schemas up to _000027)
│   │   │   └── 2024_01_01_000027_fix_oauth_user_id_to_uuid.php            # UUID compat fix
│   │   └── (root)                # Shared: oauth_*, cache, personal_access_tokens
│   │       ├── 2026_05_21_014519_create_oauth_auth_codes_table.php        # UUID-compatible user_id
│   │       ├── 2026_05_21_014520_create_oauth_access_tokens_table.php     # UUID-compatible user_id
│   │       ├── 2026_05_21_014521_create_oauth_refresh_tokens_table.php
│   │       ├── 2026_05_21_014522_create_oauth_clients_table.php
│   │       └── 2026_05_21_014523_create_oauth_personal_access_clients_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php    # → calls CentralSeeder (landlord)
│       └── TenantDatabaseSeeder.php  # → seeds per-tenant data
├── routes/
│   ├── api.php                   # Central API (Tenant onboarding)
│   ├── central.php               # Additional central routes
│   ├── tenant.php                # All tenant-scoped API routes (v1)
│   └── web.php                   # Web (minimal)
├── tests/
│   ├── Unit/
│   └── Feature/
├── phpunit.xml                   # Tests always use erp_system_test DB
└── .env.example                  # Canonical environment template
```

---

## Module Structure Convention

Every module under `app/Tenants/Modules/{Module}/` **must** follow this layout:

```
{Module}/
├── Controllers/          # Thin — validation + service call only
├── Models/               # Tenant-scoped Eloquent models
├── Services/             # ALL business logic lives here
├── Resources/            # JsonResource transformers
├── Requests/             # FormRequest validation classes
└── Policies/             # (optional) Fine-grained authorization
```

---

## OAuth Migration Architecture (Critical — Two Layers)

This project uses a **two-layer OAuth migration strategy** to support both the central landlord database and per-tenant databases.

### Layer 1: Root-Level Migrations (Central/Landlord DB)
Located in `database/migrations/` (root level). These run on the **central landlord database** via `php artisan migrate`.

> **Important:** These root-level oauth_* migration files use UUID-compatible `string(100)` for `user_id` directly — no post-fix migration needed.

```php
// Root oauth_access_tokens — user_id is already string(100)
Schema::create('oauth_access_tokens', function (Blueprint $table) {
    $table->string('id', 100)->primary();
    $table->string('user_id', 100)->nullable()->index(); // UUID-compatible
    $table->unsignedBigInteger('client_id');
    $table->string('name')->nullable();
    $table->text('scopes')->nullable();
    $table->boolean('revoked');
    $table->timestamps();
    $table->dateTime('expires_at')->nullable();
});
```

### Layer 2: Tenant-Level Migrations (Per-Tenant DBs)
Located in `database/migrations/tenant/`. These run on each **tenant database** via `php artisan tenants:migrate`.

> **Important:** All tenant-level oauth_* files MUST use `Schema::hasTable()` guards to be idempotent (in case Passport published duplicate files).

```php
// Tenant oauth migration pattern — ALWAYS use hasTable guard
public function up(): void
{
    if (!Schema::hasTable('oauth_access_tokens')) {
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('user_id', 100)->nullable()->index(); // UUID-compatible
            $table->unsignedBigInteger('client_id');
            // ...
        });
    }
}
```

### Layer 3: UUID Fix Migration (Tenant DBs Only)
`2024_01_01_000027_fix_oauth_user_id_to_uuid.php` — runs in tenant migrations ONLY.

This migration is a **safety net** that alters any pre-existing `oauth_access_tokens.user_id` and `oauth_auth_codes.user_id` columns from `bigint` to `string(100)` when old Passport defaults were already applied. It uses `Schema::hasTable()` guards to be safe.

---

## Key Configuration Files

### `config/tenancy.php` — Critical Settings

```php
return [
    'tenant_model'     => App\Models\Central\Tenant::class,
    'id_generator'     => Stancl\Tenancy\UUIDGenerator::class,
    'domain_model'     => Stancl\Tenancy\Database\Models\Domain::class,
    'central_domains'  => ['localhost', 'api.erp-system.test'],

    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    'database' => [
        // MUST match DB_CONNECTION in .env — do NOT hardcode 'central'
        'central_connection' => env('DB_CONNECTION', 'pgsql'),
        'prefix'             => 'tenant_',
        'managers' => [
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'migration_parameters' => [
        '--force'    => true,
        '--path'     => [database_path('migrations/tenant')],  // tenant migrations ONLY
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\Seeders\TenantDatabaseSeeder',
    ],
];
```

### `bootstrap/app.php` — Route Registration (Laravel 11)

```php
// tenant.php MUST be manually required in the 'then:' closure
// It is NOT auto-loaded like api.php or web.php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
        then: function () {
            require __DIR__.'/../routes/tenant.php';   // ← manual
        },
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ...
```

### `phpunit.xml` — Test Database Isolation (P0)

Tests **always** point to `erp_system_test` — never the development database.

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST"       value="127.0.0.1"/>
<env name="DB_PORT"       value="5433"/>
<env name="DB_DATABASE"   value="erp_system_test"/>
<env name="DB_USERNAME"   value="erp_user"/>
<env name="DB_PASSWORD"   value="erp_secret"/>
```

---

## Environment Variables (`.env`)

Copy from `.env.example`. Key blocks:

```ini
APP_NAME="ERP Enterprise"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Central Landlord Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=erp_system
DB_USERNAME=erp_user
DB_PASSWORD=erp_secret

# Tenancy
TENANCY_DATABASE_MANAGER=pgsql
TENANCY_CENTRAL_DOMAINS=localhost,api.erp-system.test

BROADCAST_DRIVER=log
CACHE_STORE=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Passport — Personal Access Client (auto-generated by passport:install)
PASSPORT_PERSONAL_ACCESS_CLIENT_ID=
PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=

# Passport — Password Grant (deterministic for dev/test)
# Create with: php artisan passport:client --password
PASSPORT_PASSWORD_CLIENT_ID=33
PASSPORT_PASSWORD_CLIENT_SECRET=b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7
```

---

## First-Time Setup — Exact Command Sequence

Run these commands **in this exact order**. Deviating causes cascading errors.

```bash
# 1. Copy environment template
cp .env.example .env

# 2. Install Composer dependencies
composer install

# 3. Generate app encryption key
php artisan key:generate

# 4. Generate Passport RSA keys and create default OAuth clients
#    ⚠️  Use passport:install ONCE on a fresh project — it also runs oauth_* migrations.
#    After this, NEVER run passport:install --force again (creates duplicate migration files).
php artisan passport:install

# 5. Create the password grant client (note the ID/secret printed — add to .env)
php artisan passport:client --password

# 6. Create central landlord tables (tenants, domains)
#    ⚠️  migrations/central/ is NOT auto-discovered by 'migrate'
php artisan migrate --path=database/migrations/central

# 7. Run root-level shared migrations (oauth_*, cache, personal_access_tokens)
php artisan migrate

# 8. Seed the landlord DB — this CREATES the tenant databases
php artisan db:seed

# 9. Migrate every tenant database (databases now exist after Step 8)
php artisan tenants:migrate

# 10. Seed tenant-specific data (users, roles, demo data)
php artisan tenants:seed

# 11. Copy client ID/secrets from Steps 4–5 into .env, then clear config cache:
php artisan config:clear

# 12. Start the development server
php artisan serve
```

> **Deterministic Passport Credentials:** For dev/test repeatability, after step 5 you can re-seed `oauth_clients` via `DatabaseSeeder` to force the ID to `33` and use the shared secret defined in `.env.example`.

---

## Route Architecture

### `routes/tenant.php` — All tenant-scoped endpoints

Middleware stack:
```php
Route::middleware(['api', InitializeTenancyByHandle::class])
    ->prefix('api/v1')
    ->group(function () {

        // Public (no Passport)
        Route::post('/auth/login', ...);
        Route::get('/public/job-vacancies', ...);

        // Protected (Passport)
        Route::middleware('auth:api')->group(function () {
            Route::apiResource('users', UserController::class);
            // ... all other resource routes
        });
    });
```

**All tenant routes are prefixed `/api/v1/` and require the `X-Tenant-Handle` header.**

### Active Modules & Route Prefixes

| Module | Route Prefix | Notes |
|---|---|---|
| IAM | `users`, `roles`, `workflow-statuses` | Auth at `/auth/*` |
| HRM | `employees`, `departments`, `positions`, `leaves`, `payroll-periods`, `payslips` | |
| Recruitment | `job-vacancies`, `applications`, `quizzes`, `interviews` | Public career portal at `/public/` |
| FMS | `accounts`, `ledger` | |
| Sales | `customers`, `leads`, `orders` | |
| Fleet | `vehicles`, `maintenance-logs`, `fuel-logs` | |
| Assets | `assets` | `POST /assets/{id}/depreciate` |
| Inventory | `products`, `stock-movements` | |
| Projects | `projects`, `tasks`, `timesheets` | |
| Approvals | `approval-workflows`, `approval-requests` | |
| eDocuments | `folders`, `documents` | |
| CMS | `cms-folders`, `cms-documents` | Checkout/checkin pattern |
| Reporting | `dashboards`, `widgets` | |

---

## Model Patterns

### Central (Landlord) Tenant Model

```php
// app/Models/Central/Tenant.php
namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = ['id', 'handle', 'name', 'data'];

    public static function getCustomColumns(): array
    {
        return ['id', 'handle', 'name'];
    }
}
```

### Tenant-Scoped Model Template

```php
// app/Tenants/Modules/{Module}/Models/{Model}.php
namespace App\Tenants\Modules\{Module}\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;

class {Model} extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [/* ... */];

    // Use UUID primary key
    public $incrementing = false;
    protected $keyType = 'string';
}
```

### `BelongsToTenant` Trait

```php
// app/Models/Traits/BelongsToTenant.php
// Wraps stancl's trait — use THIS version, not the package one directly
use App\Models\Traits\BelongsToTenant;
```

### `Auditable` Trait

Automatically fires on `created`, `updated`, `deleted`. Logs to `Log::info()` in current implementation.
```php
use App\Models\Traits\Auditable;
```

---

## Passport Rules (Critical)

### Initial Setup (Fresh Project — ONCE)
```bash
# ✅ Generates RSA keys + runs oauth_* migrations once
php artisan passport:install

# Then create the password grant client
php artisan passport:client --password
```

### After Initial Setup — Keys Only
```bash
# ✅ Safe — regenerates RSA keys ONLY, no migration files touched
php artisan passport:keys --force

# ❌ NEVER — republishes migration files with NEW timestamps every run
# Creates duplicate oauth_* migration files → SQLSTATE[42P07] on next migrate
php artisan passport:install --force
```

> `Passport::ignoreMigrations()` **does not exist** in Passport 12.x / Laravel 11.
> If duplicate migrations were published, patch each one with `if (! Schema::hasTable(...))` guard.

### Why Two Sets of OAuth Migrations Exist

This project intentionally has **two sets** of oauth_* migration files:

| Set | Location | Runs On |
|---|---|---|
| Root (`2026_05_21_01452*`) | `database/migrations/` | Central landlord DB (via `php artisan migrate`) |
| Tenant (`2016_06_01_0000*`) | `database/migrations/tenant/` | Each tenant DB (via `php artisan tenants:migrate`) |

Both sets use `string(100)` for `user_id` to be UUID-compatible from the start.
Tenant-level files use `Schema::hasTable()` guards to be fully idempotent.

---

## Security — OAuth Key Files

`php artisan passport:install` (or `passport:keys`) generates two RSA-2048 key files:

| File | Purpose | Sensitivity |
|---|---|---|
| `storage/oauth-private.key` | Signs new JWT access tokens | 🔴 **SECRET — never commit** |
| `storage/oauth-public.key` | Verifies JWT access tokens | 🟡 Less sensitive, still exclude from git |

### `.gitignore` must include:
```
/storage/oauth-private.key
/storage/oauth-public.key
```

> **Laravel's default `.gitignore` does NOT exclude these files.** You must add them manually on every new project.

### If Keys Were Accidentally Committed to Git
1. Remove from tracking (keep local files):
   ```bash
   git rm --cached storage/oauth-private.key storage/oauth-public.key
   git commit -m "security: remove oauth keys from tracking"
   git push
   ```
2. Regenerate new keys immediately (old tokens become invalid):
   ```bash
   php artisan passport:keys --force
   ```
3. All users will need to log in again — their existing tokens are signed with the compromised key.

### Each Environment Gets Its Own Keys
- Development, staging, and production each have **different** key pairs
- Never copy keys between environments
- In CI/CD and production, inject keys via environment variables or a secrets manager (e.g., AWS Secrets Manager, Vault)

---

## Providers

| Provider | Purpose |
|---|---|
| `CentralServiceProvider` | Landlord-level bootstrapping (currently minimal) |
| `TenantServiceProvider` | Per-tenant bootstrapping — registered in `config/tenancy.php` |

---

## Testing

- Test runner: **Pest PHP**
- DB always: `erp_system_test` (enforced by `phpunit.xml`)
- Suite locations: `tests/Unit/` and `tests/Feature/`
- Run tests: `php artisan test`
- **Never** run tests against `erp_system` (dev) or `erp_system_prod`

---

## Tenancy Artisan Commands Reference

```bash
php artisan tenants:list            # List all registered tenants
php artisan tenants:migrate         # Run tenant migrations on all tenants
php artisan tenants:rollback        # Rollback tenant migrations
php artisan tenants:seed            # Seed all tenant databases
php artisan tenants:migrate-fresh   # Drop & recreate all tenant tables
php artisan tenants:run {command}   # Run any artisan command per-tenant
```

---

## Common Errors & Quick Fixes

| Error | Root Cause | Fix |
|---|---|---|
| `relation "tenants" does not exist` | Central migrations never ran | `php artisan migrate --path=database/migrations/central` |
| `relation "users" does not exist` on login | `tenants:migrate` ran before `db:seed` | `db:seed` → `tenants:migrate` → `tenants:seed` |
| `SQLSTATE[42P07]: relation "oauth_*" already exists` | `passport:install --force` ran multiple times | Add `Schema::hasTable()` guards to duplicate migration files |
| `Call to undefined method Passport::ignoreMigrations()` | Method removed in Passport 12.x | Remove the call; use `Schema::hasTable()` guard pattern |
| `Database connection [central] not configured` | Hardcoded `'central'` in `config/tenancy.php` | Set `'central_connection' => env('DB_CONNECTION', 'pgsql')` |
| `Class not found: MySQLDatabaseManager` | Wrong namespace in tenancy.php | Remove `\Database` from the namespace string |
