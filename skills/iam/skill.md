---
name: identity-and-access-management
description: Manage users, roles, permissions, workflow statuses, and authentication. The core security layer used by every other module.
---
# Identity & Access Management (IAM)

Use this skill when working on users, roles, permissions, workflow statuses, password reset, or any cross-module authorization concern.

## Module surface (shipped)

| Layer | Path |
|---|---|
| Controllers | `app/Tenants/Modules/IAM/Controllers/{AuthController, UserController, RoleController, PermissionController, WorkflowStatusController}.php` |
| Services | `app/Tenants/Modules/IAM/Services/{UserService, RoleService, ...}.php` |
| Resources | `app/Tenants/Modules/IAM/Resources/{UserResource, RoleResource, ...}.php` |
| Requests | `app/Tenants/Modules/IAM/Requests/{StoreWorkflowStatusRequest, ...}.php` |
| Models | `app/Models/Tenant/{User, Role, Permission, WorkflowStatus}.php` (plus pivots `user_has_roles`, `role_has_permissions`) |
| Policies | `app/Policies/{UserPolicy, RolePolicy, ...}.php` |
| Migrations | `database/migrations/tenant/2024_01_01_000002_create_users_table.php` + `_000003_create_rbac_tables.php` |
| Seeder | `database/seeders/TenantDatabaseSeeder.php` (seeds permissions, roles, admin user) |
| Pages | `frontend/pages/settings/users.vue`, `frontend/pages/settings/roles.vue` |

### Routes (in `routes/tenant.php`, inside `auth:api` group)

```
POST   /auth/login
POST   /auth/logout
GET    /auth/me
POST   /auth/refresh

GET|POST|PUT|DELETE  /users
POST                  /users/{user}/reset-password     ← declared BEFORE apiResource
GET|POST|PUT|DELETE  /roles
GET                   /permissions
GET|POST|PUT|DELETE  /workflow-statuses
```

## Core contracts

### 1. `hasPermission()` on User
The User model implements its own permission check — Laravel Gate is not the authority:

```php
public function hasPermission(string $permission): bool
{
    return $this->roles()
        ->whereHas('permissions', fn ($q) => $q->where('slug', $permission))
        ->exists();
}
```

Form Requests gate on this directly: `return $this->user()?->hasPermission('iam.users.write') ?? false;`.

### 2. Permission slug grammar — `module.feature.action`
| Example | Meaning |
|---|---|
| `iam.users.read` | Read any user |
| `iam.users.write` | Create or update users |
| `iam.users.delete` | Soft-delete a user |
| `hrm.employee.read.self` | Self-service variant; pair with ownership check in the policy |
| `settings.read` / `settings.write` | Cross-module settings hub |

Slugs are seeded via `Permission::updateOrCreate(['slug' => ...], [...])` and assigned to the admin role via `$role->permissions()->syncWithoutDetaching($ids)`. See `database/seeders/{IamPermissionSeeder, SettingsPermissionSeeder, CrmPermissionSeeder, InventoryPermissionSeeder}.php` for the canonical pattern.

### 3. Password is a `'hashed'` cast (P0)
`User` declares `protected $casts = ['password' => 'hashed', ...];`. **Always pass plaintext.** Calling `Hash::make()` before assignment causes double-hashing and silent "Invalid credentials" on login. To repair an already-broken row:

```php
$user->forceFill(['password' => $plain])->save();   // cast hashes once
```

### 4. UUID PK + soft delete + audit
```php
use HasApiTokens, HasFactory, Notifiable, BelongsToTenant, Auditable, SoftDeletes;
protected $keyType = 'string';
public $incrementing = false;
```

### 5. Roles → Permissions pivot
- `user_has_roles` (`user_id`, `role_id`)
- `role_has_permissions` (`role_id`, `permission_id`)
- Seeded admin role: `slug = 'admin'` — receives all permissions in every seeder.
- Super-admin override (frontend): `authStore.isAdmin` returns `true` when role slug is `'admin'` or `'super-admin'`.

## Frontend integration

- **`stores/auth.ts`** owns the user + token + permissions flat set. `hasPermission(slug)` returns `true` if user is super-admin OR slug is in the flattened set.
- **`pages/settings/users.vue`** — card grid + search + status filter. Inline `useApi()` calls (no dedicated composable):
  - `GET /users`, `GET /roles`, `POST /users`, `PUT /users/:id`, `POST /users/:id/reset-password`, `DELETE /users/:id`.
- **Sidebar entry** (`layouts/default.vue` → `configurations` group):
  ```ts
  { label: 'User Directory', icon: 'ti-users-group', route: '/settings/users', permission: 'iam.users.read' }
  ```

## Critical rules

1. **`hasPermission()`, not `can()`** for the cross-module settings hub. The `/settings` endpoints intentionally bypass Gate because the policies aren't registered for the `Setting` model — they call `$this->user()?->hasPermission('settings.write')` directly inside the FormRequest.
2. **Route ordering (P0)**: `POST /users/{user}/reset-password` must come **before** `Route::apiResource('users', ...)`. Otherwise Laravel matches `reset-password` as `{user}`.
3. **No `id` on Central Tenant**: when joining audit logs back to the tenant identity, use `$centralTenant->getKey()` (returns the handle).
4. **Auditable**: `User` uses the trait — every create/update/delete fires `Log::info(...)` with the actor + delta. Don't strip it.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| 403 on `/users` for a user with the role | Role missing the `iam.users.read` permission row | Re-seed: `php artisan tenants:seed --class=TenantDatabaseSeeder` |
| New user can't log in but admin can | Service called `Hash::make()` on plaintext → double-hash | Remove the `Hash::make()`; `forceFill(['password' => $plain])->save()` the broken row |
| `POST /users/{user}/reset-password` returns 404 | Route declared after `apiResource('users', ...)` | Move it above the resource registration |
| Token expired but no rotation | `useApi()` not used (someone called `$fetch` directly) | Route the call through `useApi()` — it owns the single-flight 401-retry |
| Tenant mismatch (valid token, 403 on tenant data) | `X-Tenant-Handle` missing or different from the token's tenant | Confirm `tenantStore.activeHandle` matches the user's home tenant |
| Sidebar items hidden for admin | Permission array didn't include the admin-visible slugs | Items with `permission: 'x'` are gated by `hasPermission` — super-admins still pass because of the role short-circuit |

## Read next
- [`employee_role.md`](./employee_role.md), [`employee_role_flow.md`](./employee_role_flow.md), [`employee_role_testing.md`](./employee_role_testing.md) — employee self-service role
- [`rules.md`](./rules.md) — full RBAC spec
- [`flow.md`](./flow.md) — login + permission-check Mermaid flow
- [`rules/auth/skill.md`](../../rules/auth/skill.md) — Passport setup + hashed-cast contract
