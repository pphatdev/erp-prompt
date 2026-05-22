# Skill: Authentication & Security Implementation

## Context
Use this skill when implementing login flows, managing user sessions, or integrating third-party identity providers (SSO). This ensures the ERP remains secure and compliant with OAuth2 standards while maintaining strict multi-tenant isolation.

## Guidelines

### 1. Implementing Sign Up with OTP
- **OTP Generation**: Use a secure random 6-digit generator. Store the OTP in **Redis** with a short TTL (e.g., 5-10 minutes) keyed by the user's email or phone number.
- **Delivery**: Use a notification service (Mail or SMS) to deliver the OTP immediately after the initial registration step.
- **Verification**: Create a dedicated `verify-otp` endpoint that checks the submitted code against the cache and activates the account upon success.

### 2. Implementing Sign In/Sign Out
- **Backend**: Use Passport's `password_grant` client. Ensure the `tenant_id` is verified during the authentication process via the `X-Tenant-Handle` header (handled by `InitializeTenancyByHandle` middleware).
- **Deterministic Credentials**: For development, testing, and new setups, always initialize the `.env` configuration with:
  ```env
  PASSPORT_PASSWORD_CLIENT_ID=33
  PASSPORT_PASSWORD_CLIENT_SECRET=b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7
  ```
- **Frontend**: Handle the `401 Unauthorized` response by redirecting to the login page and clearing the local state.

### 3. Handling Refresh Tokens
- **Rotation**: Implement refresh token rotation to improve security.
- **Logic**: Use an Axios/Fetch interceptor in the frontend to transparently refresh the `access_token` when it expires.

### 4. SSO & External Auth
- **Socialite**: Use `laravel/socialite` for OIDC/OAuth2 providers.
- **Configuration**: Tenant-specific SSO settings (Client ID, Secret, Endpoint) must be retrieved from the tenant's database connection.

### 5. Implementing Fine-Grained Authorization
- **Model Policies**: Generate policies for all major models (`php artisan make:policy`). Ensure the `viewAny` and `create` methods check against the user's assigned permissions.
- **Frontend Permission Checks**: Use a global `v-can` directive or a computed `hasPermission(name)` helper to conditionally render UI elements like "Edit" buttons or "Delete" actions.
- **Route Protection**: Wrap all sensitive routes in the `can:` middleware.

---

## Passport Installation Rules

> **CRITICAL**: Follow these rules to avoid creating duplicate migration files that break `php artisan migrate`.

### Initial Setup (Fresh Project — run ONCE)
```bash
# Step 1 — Generate RSA encryption keys + run oauth_* migrations ONCE
#           This also creates a personal access client automatically.
php artisan passport:install

# Step 2 — Create the password grant client (use deterministic ID if seeder expects it)
#           Note the client ID and secret printed — add them to .env
php artisan passport:client --password
```

> ⚠️ **`passport:install` MUST only be run ONCE per environment.** It publishes
> Passport's oauth_* migration files with a timestamp. Running it again creates
> **new** migration files with **new** timestamps alongside the old ones.

### Re-Generating Keys Only (After Initial Setup)
```bash
# ✅ CORRECT — only regenerates RSA encryption keys, does NOT touch migration files
php artisan passport:keys --force

# ❌ WRONG — republishes migration files with NEW timestamps every single run
# This creates duplicate oauth_* migration files (e.g. _015030_, _015038_, _015136_...)
# Each duplicate will then fail with: SQLSTATE[42P07]: relation "oauth_auth_codes" already exists
php artisan passport:install --force
```

### If Duplicate Migrations Were Already Created
If `passport:install --force` was accidentally run multiple times, every newly published set of `oauth_*` migration files must be patched with a `Schema::hasTable()` guard:

```php
// Pattern for ALL duplicate oauth_* migration files
public function up(): void
{
    if (! Schema::hasTable('oauth_auth_codes')) {  // check before create
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('user_id', 100)->nullable()->index();  // UUID-compatible
            $table->unsignedBigInteger('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
    }
}

public function down(): void
{
    // Intentionally empty — managed by the original migration
}
```

> `Passport::ignoreMigrations()` **does not exist** in Passport 12.x (Laravel 11). Do not use it — it will throw `Call to undefined method`. The `Schema::hasTable()` guard pattern above is the correct workaround.

---

## OAuth Migration Architecture (Two-Layer Strategy)

This project uses a **two-layer OAuth migration strategy**. Do NOT be confused by seeing oauth_* files in two different locations — this is intentional.

### Layer 1: Root Migrations → Central/Landlord DB
```
database/migrations/
├── 2026_05_21_014519_create_oauth_auth_codes_table.php
├── 2026_05_21_014520_create_oauth_access_tokens_table.php       ← user_id string(100)
├── 2026_05_21_014521_create_oauth_refresh_tokens_table.php
├── 2026_05_21_014522_create_oauth_clients_table.php
└── 2026_05_21_014523_create_oauth_personal_access_clients_table.php
```
These run via `php artisan migrate` and create OAuth tables in the **central landlord database**.

### Layer 2: Tenant Migrations → Per-Tenant DBs
```
database/migrations/tenant/
├── 2016_06_01_000001_create_oauth_auth_codes_table.php          ← hasTable guard
├── 2016_06_01_000002_create_oauth_access_tokens_table.php       ← hasTable guard + string(100)
├── 2016_06_01_000003_create_oauth_refresh_tokens_table.php      ← hasTable guard
├── 2016_06_01_000004_create_oauth_clients_table.php             ← hasTable guard
├── 2016_06_01_000005_create_oauth_personal_access_clients_table.php  ← hasTable guard
└── 2024_01_01_000027_fix_oauth_user_id_to_uuid.php              ← bigint → string(100) safety net
```
These run via `php artisan tenants:migrate` for each tenant DB.

### UUID Compatibility Rule
All `user_id` columns in oauth_* tables **must** be `string(100)` — not `unsignedBigInteger` — because User models in this project use UUID primary keys. The tenant migration `_000027_fix_oauth_user_id_to_uuid.php` acts as a safety net to repair any tables that were created with the wrong type.

---

## Best Practices
- **Never Trust the Frontend**: Always re-verify permissions in the backend Service layer, even if the UI hid the button.
- **Cache Permissions**: Use Redis to cache user permissions for the duration of the session to avoid redundant database queries.
- **Password Hashing**: Always use `bcrypt` or `argon2id` (Laravel default).
- **Never Double-Hash Passwords (P0)**: The `User` model declares `'password' => 'hashed'` in its `casts()`. That cast hashes the value once on assignment via `Hash::isHashed()` detection. **Do not** call `Hash::make()` yourself before assigning. The detection guard is driver- and cost-sensitive — any mismatch (different `BCRYPT_ROUNDS`, hasher driver swap between processes, etc.) causes the cast to re-hash an already-hashed value, producing `Hash::make(Hash::make($plain))`. `Hash::check()` then fails and login returns "Invalid credentials." Pass plaintext to `User::create([...])` / `$user->update([...])` and let the cast hash it. This applies to **every** attribute with the `hashed` cast, not just `password`.
- **Session Security**: Set `session.secure` and `session.http_only` to `true` in production.
- **Audit Logs**: Log every successful login, failed attempt, and password change.
- **Scoped Permissions**: Use Passport **Scopes** to limit the actions an access token can perform.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `SQLSTATE[42P07]: relation "oauth_auth_codes" already exists` | `passport:install --force` ran multiple times, creating duplicate timestamped migration files | Apply `Schema::hasTable()` guard to every duplicate file, or delete duplicates if originals already ran |
| `Call to undefined method Passport::ignoreMigrations()` | Method removed in Passport 12.x | Remove the call; use `Schema::hasTable()` guard in migration files instead |
| `Token Expired` | `access_token` TTL exceeded | Check `expires_in` value and ensure frontend refresh interceptor is working |
| `Invalid Tenant` — valid token gets 403 | Token's `tenant_id` doesn't match current active tenant | Verify `X-Tenant-Handle` header is present and `InitializeTenancyByHandle` middleware is on the route |
| `SSO Callback Failure` | Mismatched `REDIRECT_URI` | Check the redirect URI in both the identity provider config and Laravel `.env` |
| `oauth_access_tokens.user_id column type mismatch` | Passport default used `unsignedBigInteger` but users have UUID keys | Run `php artisan tenants:migrate` — migration `_000027_fix_oauth_user_id_to_uuid.php` will fix it |
| Freshly-created user gets "Invalid credentials" but the seeded admin logs in fine | Double-hashed password. A service called `Hash::make()` on plaintext, then the model's `'password' => 'hashed'` cast re-hashed the result because `Hash::isHashed()` failed its driver/cost verification at write time. Stored value is `bcrypt(bcrypt($plain))`. | (1) Remove the manual `Hash::make()` from the service — pass plaintext to `User::create()`. (2) Reset the password for any existing user already saved under the broken code path: `$user->forceFill(['password' => $plain])->save();` (the cast will hash it correctly once). |
