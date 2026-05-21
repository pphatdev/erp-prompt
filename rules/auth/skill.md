# Skill: Authentication & Security Implementation

## Context
Use this skill when implementing login flows, managing user sessions, or integrating third-party identity providers (SSO). This ensures the ERP remains secure and compliant with OAuth2 standards while maintaining strict multi-tenant isolation.

## Guidelines

### 1. Implementing Sign Up with OTP
- **OTP Generation**: Use a secure random 6-digit generator. Store the OTP in **Redis** with a short TTL (e.g., 5-10 minutes) keyed by the user's email or phone number.
- **Delivery**: Use a notification service (Mail or SMS) to deliver the OTP immediately after the initial registration step.
- **Verification**: Create a dedicated `verify-otp` endpoint that checks the submitted code against the cache and activates the account upon success.

### 2. Implementing Sign In/Sign Out
- **Backend**: Use Passport's `personal_access_tokens` or `password_grant`. Ensure the `tenant_id` is verified during the authentication process.
- **Deterministic Credentials**: For development, testing, and new setups, always initialize the `.env` configuration with:
  ```env
  PASSPORT_PASSWORD_CLIENT_ID=33
  PASSPORT_PASSWORD_CLIENT_SECRET=b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7
  ```
- **Frontend**: Handle the `401 Unauthorized` response by redirecting to the login page and clearing the local state.

### 2. Handling Refresh Tokens
- **Rotation**: Implement refresh token rotation to improve security.
- **Logic**: Use an Axios/Fetch interceptor in the frontend to transparently refresh the `access_token` when it expires.

### 3. SSO & External Auth
- **Socialite**: Use `laravel/socialite` for OIDC/OAuth2 providers.
- **Configuration**: Tenant-specific SSO settings (Client ID, Secret, Endpoint) must be retrieved from the tenant's database connection.

### 4. Implementing Fine-Grained Authorization
- **Model Policies**: Generate policies for all major models (`php artisan make:policy`). Ensure the `viewAny` and `create` methods check against the user's assigned permissions.
- **Frontend Permission Checks**: Use a global `v-can` directive or a computed `hasPermission(name)` helper to conditionally render UI elements like "Edit" buttons or "Delete" actions.
- **Route Protection**: Wrap all sensitive routes in the `can:` middleware.

## Passport Installation Rules

> **CRITICAL**: Follow these rules to avoid creating duplicate migration files that break `php artisan migrate`.

### Initial Setup (Fresh Project)
```bash
# Step 1 — Generate encryption keys and run oauth_* migrations ONCE
php artisan passport:install

# Step 2 — Create the password grant client (use deterministic ID if seeder expects it)
php artisan passport:client --password
```

### Re-Generating Keys Only (After Initial Setup)
```bash
# ✅ CORRECT — only regenerates encryption keys, does NOT touch migration files
php artisan passport:keys --force

# ❌ WRONG — republishes migration files with NEW timestamps every single run
# This creates duplicate oauth_* migration files (e.g. _015030_, _015038_, _015136_...)
# Each duplicate will then fail with: SQLSTATE[42P07]: relation "oauth_auth_codes" already exists
php artisan passport:install --force
```

### If Duplicate Migrations Were Already Created
If `passport:install --force` was accidentally run multiple times, every newly published set of `oauth_*` migration files must be patched with a `Schema::hasTable()` guard:

```php
// Pattern for all duplicate oauth_* migration files
public function up(): void
{
    if (! Schema::hasTable('oauth_auth_codes')) {  // check before create
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            // ... columns
        });
    }
}

public function down(): void
{
    // Intentionally empty — managed by the original migration
}
```

> `Passport::ignoreMigrations()` **does not exist** in Passport 11.x (Laravel 11). Do not use it — it will throw `Call to undefined method`. The `Schema::hasTable()` guard pattern above is the correct workaround.

## Best Practices
- **Never Trust the Frontend**: Always re-verify permissions in the backend Service layer, even if the UI hid the button.
- **Cache Permissions**: Use Redis to cache user permissions for the duration of the session to avoid redundant database queries.
- **Password Hashing**: Always use `bcrypt` or `argon2id` (Laravel default).
- **Session Security**: Set `session.secure` and `session.http_only` to `true` in production.
- **Audit Logs**: Log every successful login, failed attempt, and password change.
- **Scoped Permissions**: Use Passport **Scopes** to limit the actions an access token can perform.

## Troubleshooting
- **`SQLSTATE[42P07]: relation "oauth_auth_codes" already exists`**: `passport:install --force` was run more than once. New migration files with different timestamps were created. Apply the `Schema::hasTable()` no-op guard to every duplicate file, or delete the duplicates if the original `014519_*` migrations already ran.
- **`Call to undefined method Passport::ignoreMigrations()`**: This method does not exist in Passport 11.x. Remove the call. Use the `Schema::hasTable()` guard in migration files instead.
- **Token Expired**: If a user is logged out unexpectedly, check the `expires_in` value and the frontend refresh logic.
- **Invalid Tenant**: If a valid token fails with a 403, verify that the token's `tenant_id` matches the current active tenant in the request.
- **SSO Callback Failure**: Check the `REDIRECT_URI` configuration in both the identity provider and the Laravel `.env` file.
