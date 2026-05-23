# Skill: Backend API & Business Logic Implementation

## Context
Use this skill when implementing new API endpoints, business services, or database interactions. The project backend **MUST** be implemented exclusively in **Laravel 11+** (PHP 8.2+). This ensures that the code follows enterprise-grade standards, leverages Laravel's native ecosystem, maintains multi-tenant isolation, and is highly maintainable.

## Guidelines

### 1. Controller Implementation (API Surface)
- **Thin Controllers**: Limit controllers to request validation and service calling.
- **Form Requests**: Always use dedicated Form Request classes for validation.
- **Resourceful Routing**: Stick to standard CRUD methods (`index`, `store`, `show`, `update`, `destroy`).
- **Response Handling**: Always return `JsonResource` or `ResourceCollection`.

### 2. Service Layer (Business Domain)
- **Logic Isolation**: Move all business logic, calculations, and external integrations to Service classes.
- **Atomicity**: Ensure service methods are atomic. Use `DB::transaction()` for operations spanning multiple tables.
- **Dependencies**: Inject models or other services via the constructor.
- **Respect Model Casts (P0)**: Do not re-implement transformations the model already declares via `casts()`. Specifically:
  - Never call `Hash::make()` on an attribute the model casts as `'hashed'` (e.g. `User::password`). The cast hashes plaintext exactly once; double-hashing breaks `Hash::check()` and produces silent "Invalid credentials" on login. See [`rules/auth/skill.md`](../auth/skill.md) for the full story.
  - Never `json_encode()` an attribute cast as `'array'` / `'json'` — the cast serializes on write.
  - Never `Crypt::encrypt()` an attribute cast as `'encrypted'`.
  - General rule: if the model casts an attribute, the service passes the **decoded/plaintext** value and lets Eloquent's setAttribute do the work.

### 3. Model & Database Patterns
- **UUIDs**: Use UUIDs as primary keys for all models.
- **Tenancy**: Ensure the `BelongsToTenant` trait is applied to all tenant-scoped models.
- **Soft Deletes**: Apply `SoftDeletes` to preserve business audit trails.
- **Accessors/Mutators**: Use the new PHP 8.x `Attribute` syntax for model data manipulation.

### 4. API Design & Security
- **CamelCase**: Ensure API responses use `camelCase` for keys.
- **Paginated List Responses**: Index/list endpoints must use the following standard pagination envelope format:
  ```json
  {
      "data": [],
      "pagination": {
          "page": 1,
          "limit": 10,
          "total": 14,
          "totalPages": 2
      }
  }
  ```
- **Authentication**: Use Laravel Passport for API authentication.
- **Authorization**: Use Policies (`php artisan make:policy`) to authorize actions based on tenant permissions.

## Best Practices
- **Strict Typing**: Leverage PHP 8.3 features like constructor property promotion and strict return types.
- **Custom Exceptions**: Throw domain-specific exceptions (e.g., `PaymentFailedException`) instead of generic ones.
- **Early Returns**: Use guard clauses to handle edge cases early in your methods.
- **Documentation**: Use JSDoc-style comments for complex business logic explanations within services.

## Troubleshooting
- **First Build `.env` Generation**: When initializing the project, always duplicate `.env.example` to `.env` and run `php artisan key:generate` before doing anything else. Then run `php artisan passport:install` (once) to generate RSA keys and oauth_* tables. See `rules/backend/backend_setup.md` for the full 12-step sequence.
- **Missing `artisan` File**: If `composer install` fails at `postAutoloadDump` with `Could not open input file: artisan`, ensure the Laravel 11 `artisan` file exists at the project root. Recreate it with standard Laravel boilerplate if necessary.
- **Missing Base Controller**: If you see `Class "App\Http\Controllers\Controller" not found`, ensure the abstract `Controller.php` file exists in `app/Http/Controllers/`.
- **Composer SSL/TLS Errors**: On Windows environments, if Composer throws OpenSSL errors, uncomment `;extension=openssl` and `;extension_dir = "ext"` in your active `php.ini` (found via `php --ini`).
- **Required PHP Extension Errors**: If `composer` or `php artisan migrate` fail due to missing drivers or undefined functions (like `pdo_pgsql`, `mb_split`, or `openssl`), follow these steps to fix your local PHP environment:
  1. Run `php --ini` in your terminal to locate your active `php.ini` file.
  2. Open the file in a text editor and search for `;extension_dir = "ext"`. Remove the semicolon.
  3. Search for `;extension=openssl`, `;extension=pdo_pgsql`, `;extension=pgsql`, and `;extension=mbstring`.
  4. Remove the semicolon (`;`) in front of each to enable them.
  5. Save the file and restart your terminal.
- **.env Database Connection Error**: Never use `DB_CONNECTION=central`. Use the exact block below to match the Docker credentials:
  ```ini
  # Central Database
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5433
  DB_DATABASE=erp_system
  DB_USERNAME=erp_user
  DB_PASSWORD=erp_secret
  ```
- **Missing Tenant Routes**: If `routes/tenant.php` isn't accessible, ensure it is manually registered in the `then:` closure of the `withRouting()` method in `bootstrap/app.php`.
- **IDE Autocomplete Missing (Docker)**: If using Docker, ensure the `vendor/` directory is either synced to the host or run `composer install` locally on the host machine to enable IDE code intelligence.
- **Tenant Scope Missing**: If data from other tenants appears, verify the `BelongsToTenant` trait and check if the `tenant_id` is set correctly in the session.
- **Tenancy Central Connection Error**: If you see `Database connection [central] not configured`, update `config/tenancy.php` to map `'central_connection'` to `env('DB_CONNECTION')` instead of `'central'`.
- **Tenancy Database Manager Class Error**: If you get class not found errors for `MySQLDatabaseManager` in `tenancy.php`, remove the `\Database` string from the namespaces in the `managers` array.
- **N+1 Queries**: Use `Eager Loading` (`with()`) to prevent performance bottlenecks. Use the `laravel-query-detector` in development.
- **Validation Errors**: If 422 errors are unclear, ensure the Form Request's `messages()` method provides helpful feedback.
- **Transaction Deadlocks**: Keep database transactions as short as possible and avoid external API calls inside them.
- **`[object Object]` in URLs after resource action (confirm/cancel)**: The action is using `response()->json(['data' => $resource->toArray(...)])`. Calling `->toArray()` directly bypasses Laravel's resource pipeline; `whenLoaded()` sentinels (`MissingValue`) are not filtered and become `{}` in JSON. Fix: `return new XxxResource($model->load([...]))` directly. For 201 use `->response()->setStatusCode(201)`. See `skills/sales/rules.md` § *Controller response pattern*.
- **`relation "domains" does not exist`**: The central `domains` table has never been migrated. Run `php artisan migrate --path=database/migrations/central --force`. Ensure `CentralServiceProvider::boot()` calls `$this->loadMigrationsFrom(database_path('migrations/central'))` so future `php artisan migrate` picks it up automatically.
- **Subscription not provisioned after invoice confirm**: `InvoiceService::confirm` must call `activateLinkedSubscription()` after its transaction commits. This auto-confirms any `new` subscription linked via `invoice → order → subscription`, which dispatches `SubscriptionConfirmed` outside any open transaction. See `skills/sales/rules.md` § *Invoice → Subscription auto-activation*.
- **`SubscriptionConfirmed` listener sees uncommitted subscription row**: `SubscriptionService::confirm` must wrap the `UPDATE` in its own `DB::transaction` and dispatch the event **after** that transaction commits — not inside a parent transaction. The listener opens its own DB connections (central + new tenant) and needs to see committed data.
- **`relation "tenants" does not exist` on `tenants:migrate`**: The central migrations at `database/migrations/central/` were never run. This path is **not** auto-discovered by `php artisan migrate`. Run `php artisan migrate --path=database/migrations/central` first. See `rules/tenancy/skill.md` for the full 8-step sequence.
- **`relation "users" does not exist` on login**: `tenants:migrate` was executed before `db:seed`. The central seeder provisions tenant databases — if migrate runs before seed, the databases are empty. Correct order: `db:seed` → `tenants:migrate` → `tenants:seed`.
- **`SQLSTATE[42P07]: relation "oauth_auth_codes" already exists`**: `passport:install --force` was run multiple times, each time publishing new migration files with fresh timestamps. Apply a `Schema::hasTable()` guard to each duplicate file. See `rules/auth/skill.md` for the full pattern.
- **`oauth_access_tokens.user_id` type mismatch**: Passport's default uses `unsignedBigInteger` for `user_id` but this project uses UUID string keys. Tenant migration `_000027_fix_oauth_user_id_to_uuid.php` patches this automatically — run `php artisan tenants:migrate` to apply it.
- **`passport:setup` command not found**: This project uses `passport:install` (not `passport:setup`). Run `php artisan passport:install` for initial setup, then `php artisan passport:client --password` to create the password grant client.
- **New user can't login but seeded admin can**: A service is calling `Hash::make()` on the password before assignment, and the User model already declares `'password' => 'hashed'` in `casts()`. The cast re-hashes on top of the manual hash, so `Hash::check($plain, $stored)` fails. Remove `Hash::make()` from the service — pass plaintext, let the cast hash exactly once. Existing broken rows must be re-saved with their intended plaintext (`$user->forceFill(['password' => $plain])->save()`).
- **Login fails "Invalid credentials" on a provisioned tenant**: Either the customer admin user was never created (tenant provisioned before the `TenantProvisioningService` user-creation fix) or passwords are double-hashed. Run `php artisan tenants:repair-credentials --tenant={handle}` — it self-heals both issues. See `skills/sales/rules.md` § *Repairing credentials on existing tenants*.
- **`$centralTenant->id` is null**: `App\Models\Central\Tenant` uses `handle` as its primary key. There is no `id` column. Use `$centralTenant->getKey()` (returns the handle) or `$centralTenant->handle`. Update `config/tenancy.php` `id_generator` to `null` and ensure `Tenant::$primaryKey = 'handle'` with `getTenantKeyName()` returning `'handle'`.
- **Tenant database not found after migrating to handle-PK**: Existing tenant databases were named `tenant_{uuid}` when the PK was a UUID. After migrating to `handle` as PK, Stancl looks for `tenant_{handle}`. Re-provision the customer (delete central tenant row + customer `provisioned_tenant_id`, then save customer again to trigger auto-provisioning) or manually rename the physical database.
- **`migrate:refresh` fails on central migration 000003**: The `down()` method tried to add a `NOT NULL` column to a table with existing rows. This has been fixed — `down()` is now a no-op (intentional: migration 000001.down() drops the full `tenants` table anyway, making 000003.down() redundant).
- **`POST /users/{user}/reset-password` returns 404**: The route is registered via `Route::post('/users/{user}/reset-password', ...)` and must be declared **before** `Route::apiResource('users', ...)`. Check `routes/tenant.php` ordering.

