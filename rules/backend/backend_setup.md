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
│   │   ├── tenant/               # Per-tenant schemas (30 migration files)
│   │   │   ├── 2016_06_01_000001_create_oauth_auth_codes_table.php
│   │   │   ├── 2024_01_01_000002_create_users_table.php
│   │   │   ├── 2024_01_01_000003_create_rbac_tables.php
│   │   │   └── ... (all business module schemas)
│   │   └── (root)                # Shared: oauth_*, cache, personal_access_tokens
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

# Passport — Password Grant (deterministic for dev/test)
PASSPORT_PASSWORD_CLIENT_ID=33
PASSPORT_PASSWORD_CLIENT_SECRET=b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7
```

---

## First-Time Setup — Exact Command Sequence

Run these commands in order. **Deviating from this order causes cascading errors.**

```bash
# 1. Copy environment template
cp .env.example .env

# 2. Install Composer dependencies
composer install

# 3. Generate app encryption key
php artisan key:generate

# 4. Generate Passport OAuth encryption keys (ONLY keys — no migrations)
php artisan passport:keys

# 5. Create central landlord tables (tenants, domains)
#    ⚠️  migrations/central/ is NOT auto-discovered by 'migrate'
php artisan migrate --path=database/migrations/central

# 6. Run root-level shared migrations (oauth_*, cache, personal_access_tokens)
php artisan migrate

# 7. Seed the landlord DB — this CREATES the tenant databases
php artisan db:seed

# 8. Migrate every tenant database (databases now exist after Step 7)
php artisan tenants:migrate

# 9. Seed tenant-specific data (users, roles, demo data)
php artisan tenants:seed

# 10. Start the development server
php artisan serve
```

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

### Initial Setup Only
```bash
php artisan passport:install    # Generates keys + runs oauth_* migrations ONCE
php artisan passport:client --password  # Creates password grant client
```

### After Initial Setup — Keys Only
```bash
# ✅ Safe — regenerates keys only
php artisan passport:keys --force

# ❌ NEVER — republishes migration files with NEW timestamps every run
# Creates duplicate oauth_* migration files → SQLSTATE[42P07] on next migrate
php artisan passport:install --force
```

> `Passport::ignoreMigrations()` **does not exist** in Passport 12.x / Laravel 11.
> If duplicate migrations were published, patch each one with `if (! Schema::hasTable(...))` guard.

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
