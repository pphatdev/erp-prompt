# Backend Architectural Structure: Modular Laravel

> Authoritative directory map for `backend/`. This describes the **actual** layout — not an aspirational one. Mirror it exactly when scaffolding new modules.

## Overview

The backend is a **modular monolith**. All business logic for tenant-scoped features lives under `app/Tenants/Modules/{Module}/` and is reached via routes registered in a single `routes/tenant.php`. The central (landlord) side is intentionally minimal: it owns only `tenants`, `domains`, and the OAuth client tables.

## 1. Top-level layout

```text
backend/
├── app/
│   ├── Console/Commands/             # Custom Artisan commands (e.g. tenants:repair-credentials)
│   ├── Exceptions/                   # Custom domain exceptions
│   ├── Http/
│   │   ├── Concerns/Paginates.php    # Shared paginateQuery() + paginatedResponse() trait
│   │   ├── Controllers/              # Central controllers only (Tenant onboarding) + base Controller.php
│   │   └── Middleware/               # InitializeTenancyByHandle, etc.
│   ├── Models/
│   │   ├── Central/Tenant.php        # Landlord-side: extends Stancl BaseTenant; PK = handle
│   │   ├── Tenant/                   # Tenant-scoped models (User, Setting, Customer, Product, ...)
│   │   ├── Traits/
│   │   │   ├── BelongsToTenant.php   # Wraps Stancl's trait (use THIS path)
│   │   │   └── Auditable.php         # Boot hooks for created/updated/deleted logging
│   │   └── Casts/                    # Custom Eloquent casts
│   ├── Policies/                     # All authorization policies (UserPolicy, CategoryPolicy, ...)
│   ├── Providers/
│   │   ├── CentralServiceProvider.php   # Loads central migrations via loadMigrationsFrom()
│   │   └── TenantServiceProvider.php
│   ├── Services/                     # Central-only services (rare). Most live inside modules.
│   └── Tenants/
│       └── Modules/                  # ← ALL business modules live here
│           ├── IAM/                  # Users, Roles, Permissions, Workflow statuses, Auth
│           ├── Settings/             # tenant_settings key/value store
│           ├── HRM/                  # Employees, departments, leave, payroll, recruitment
│           ├── Sales/                # Customers, quotations, orders, invoices, subscriptions
│           ├── Inventory/            # Products, categories, warehouses, suppliers, POs
│           ├── Crm/                  # Leads, opportunities, contacts, appointments, activities
│           ├── FMS/                  # Chart of accounts, journals, ledger
│           ├── Approvals/            # eApprovals workflows + actions
│           ├── Documents/            # CMS folders + documents
│           ├── EDocuments/           # Document explorer / check-in/check-out
│           ├── Projects/             # Projects, tasks, timesheets
│           ├── Fleet/                # (backend scaffold — UI planned)
│           ├── Assets/               # (backend scaffold — UI planned)
│           └── Reporting/            # Dashboards + DashboardSummaryService
├── bootstrap/
│   └── app.php                       # Manually require routes/tenant.php in the then: closure
├── config/
│   ├── tenancy.php                   # central_connection must be env('DB_CONNECTION','pgsql')
│   ├── passport.php                  # OAuth2 settings
│   ├── database.php
│   └── auth.php                      # api guard → passport; provider → App\Models\Tenant\User
├── database/
│   ├── migrations/
│   │   ├── central/                  # Landlord schema (tenants, domains, central oauth_*)
│   │   ├── tenant/                   # Per-tenant schemas — ALL tenant feature tables live here
│   │   └── (root)                    # Shared/root: oauth_*, cache, personal_access_tokens
│   └── seeders/
│       ├── DatabaseSeeder.php        # Central-side: provisions tenant DBs
│       ├── TenantDatabaseSeeder.php  # Per-tenant bootstrap (users, roles, perms, CoA, ...)
│       ├── SettingsPermissionSeeder.php
│       ├── CrmPermissionSeeder.php
│       └── InventoryPermissionSeeder.php
├── routes/
│   ├── api.php                       # Central API (tenant onboarding)
│   ├── central.php
│   ├── tenant.php                    # ← ALL tenant-scoped /api/v1/* routes
│   └── web.php
├── tests/
│   ├── Feature/Tenant/               # Pest tests, grouped by module (Crm/, Sales/, Inventory/, ...)
│   └── Unit/
└── phpunit.xml                       # Forces DB_DATABASE=erp_system_test (never dev/prod)
```

## 2. Module sub-folder convention

Every module under `app/Tenants/Modules/{Module}/` follows this layout. Folders are created **on demand** — not all modules need every folder:

```text
{Module}/
├── Controllers/      # Thin: validate, call service, return Resource
├── Services/         # All business logic. Inject via constructor. Use DB::transaction.
├── Resources/        # JsonResource transformers (response shape)
├── Requests/         # FormRequest classes (optional — controllers may inline-validate)
├── Events/           # Domain events (e.g. LeadQualified, SubscriptionConfirmed)
└── Listeners/        # Event listeners
```

**Models live in `app/Models/Tenant/`** — they are NOT placed inside the module folder. This is a deliberate convention that keeps the model layer flat and discoverable. Policies live in `app/Policies/`.

## 3. Namespace conventions

| Layer | Namespace | Example |
|---|---|---|
| Module Controller | `App\Tenants\Modules\{Module}\Controllers` | `App\Tenants\Modules\Inventory\Controllers\CategoryController` |
| Module Service | `App\Tenants\Modules\{Module}\Services` | `App\Tenants\Modules\Inventory\Services\CategoryService` |
| Module Resource | `App\Tenants\Modules\{Module}\Resources` | `App\Tenants\Modules\Inventory\Resources\CategoryResource` |
| Module FormRequest | `App\Tenants\Modules\{Module}\Requests` | `App\Tenants\Modules\IAM\Requests\StoreWorkflowStatusRequest` |
| Tenant model | `App\Models\Tenant` | `App\Models\Tenant\Category` |
| Central model | `App\Models\Central` | `App\Models\Central\Tenant` |
| Policy | `App\Policies` | `App\Policies\CategoryPolicy` |
| Trait | `App\Models\Traits` | `App\Models\Traits\BelongsToTenant` |

> Never use `App\Modules\{X}` — that namespace does not exist in this codebase.

## 4. Routing

All tenant routes live in **one file**: `routes/tenant.php`. There is **no per-module `Routes/api.php`**. The file groups routes by module via headed comments. Top of file:

```php
Route::middleware(['api', InitializeTenancyByHandle::class])
    ->prefix('api/v1')
    ->group(function () {
        // Public (no Passport)
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::get('/public/job-vacancies', ...);
        Route::get('/settings/public', [SettingController::class, 'public']);

        // Protected
        Route::middleware('auth:api')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::apiResource('roles', RoleController::class);
            Route::get('/settings', [SettingController::class, 'index']);
            Route::put('/settings', [SettingController::class, 'update']);
            Route::apiResource('modules', ModuleController::class)->only(['index']);
            Route::patch('/modules/{module}/toggle', [ModuleController::class, 'toggle']);
            Route::apiResource('categories', CategoryController::class);
            // ... all other resources
            Route::get('/dashboard/summary', DashboardSummaryController::class);
        });
    });
```

**Ordering rule (P0):** custom routes that share a path prefix with a resource MUST be declared **before** the `apiResource`. E.g. `GET /customers/check-handle` must come before `Route::apiResource('customers', ...)` — otherwise Laravel matches `check-handle` as `{customer}`.

## 5. Migrations

| Location | Purpose | Runs via |
|---|---|---|
| `database/migrations/central/` | Tenant + domain registry, central OAuth | `php artisan migrate --path=database/migrations/central` (NOT auto-discovered by plain `migrate`) |
| `database/migrations/tenant/` | Every per-tenant business table (users, products, settings, ...) | `php artisan tenants:migrate` |
| `database/migrations/` (root) | Shared infra: `oauth_*`, `cache`, `jobs`, `personal_access_tokens` | `php artisan migrate` |

There is **no `Database/Migrations/` folder inside modules**. All tenant feature tables go in `database/migrations/tenant/`, named by date prefix in the order they should run.

### Self-referential FK gotcha (PostgreSQL)

When a tenant table has a self-FK (e.g. `categories.parent_id → categories.id`, `modules.parent_id → modules.id`), split into two `Schema::` calls inside the same migration:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // ... other columns
    $table->uuid('parent_id')->nullable();
    $table->softDeletes();
});

Schema::table('categories', function (Blueprint $table) {
    $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
});
```

A single `Schema::create` with the FK inline fails on PostgreSQL because the table doesn't yet exist at FK-evaluation time.

## 6. Service-layer contract

- **Controllers are thin.** They validate, call one service method, return one Resource.
- **Services hold all business logic.** Inject via constructor: `public function __construct(private readonly OrderService $orders) {}`
- **Wrap multi-table writes in `DB::transaction`.** Required for any operation that touches more than one model.
- **Throw domain exceptions** for rule violations (`InsufficientStockException`, etc.) — not generic `\Exception`.
- **Trust model casts.** Never `Hash::make()` an attribute the model casts as `'hashed'`. Never `json_encode()` an attribute cast as `'json'` / `'array'`. Pass plaintext/decoded values; let Eloquent do the work.

## 7. Cross-module communication

Use **events + listeners**, not direct service-to-service calls.

- `LeadQualified` (CRM) → handled by Sales
- `SubscriptionConfirmed` (Sales) → handled by `TenantProvisioningService`
- `OrderConfirmed` → reserves stock in Inventory

When a listener opens a **different DB connection** (central or another tenant), the originating service MUST commit its transaction first, then dispatch the event. Dispatching inside the transaction causes the listener to read uncommitted data.

## 8. Permissions

Permission slugs follow `module.feature.action`:

```
iam.users.read | iam.users.write | iam.users.delete
inventory.category.read | inventory.category.write | inventory.category.delete
crm.leads.read | crm.opportunities.write | crm.activities.delete
settings.read | settings.write
hrm.employee.read | hrm.employee.read.self     ← .self for self-service variants
```

Seeded via dedicated seeders: `IamPermissionSeeder`, `CrmPermissionSeeder`, `InventoryPermissionSeeder`, `SettingsPermissionSeeder` — each uses `Permission::updateOrCreate(['slug' => ...], [...])` and `Role::where('slug','admin')->each(fn ($r) => $r->permissions()->syncWithoutDetaching($ids))`.

Authorization on the User model:

```php
public function hasPermission(string $permission): bool
{
    return $this->roles()
        ->whereHas('permissions', fn ($q) => $q->where('slug', $permission))
        ->exists();
}
```

FormRequest gates call it via `$this->user()?->can('iam.workflow_statuses.write')` (or `->hasPermission(...)` directly when the policy is bypassed for infrastructure endpoints like `/settings`).

## 9. Audit trail

`use App\Models\Traits\Auditable;` on every model that records business state. The trait hooks `created`, `updated`, `deleted` and writes through to the logging channel. Models confirmed using it: `User`, `Product`, `Employee`, `Customer`, `Lead`, `Order`, `Application`, `Category`, `ExchangeRate`, `StockTransfer`, `PurchaseOrder` (and most others — apply it by default).

## 10. Scaffolding a new module — file checklist

When you add a new module (say `Foo`), create:

1. `app/Tenants/Modules/Foo/` with `Controllers/`, `Services/`, `Resources/`, `Requests/` (only what you need)
2. `app/Models/Tenant/Foo.php` — `use BelongsToTenant, Auditable, SoftDeletes;` + UUID PK
3. `app/Policies/FooPolicy.php` — `view`, `create`, `update`, `delete` gated by `foo.feature.action`
4. `database/migrations/tenant/{date}_create_foos_table.php`
5. `database/seeders/FooPermissionSeeder.php` and call it from `TenantDatabaseSeeder`
6. Register routes in `routes/tenant.php` under the appropriate module-headed section
7. Add tests in `tests/Feature/Tenant/Foo/`
