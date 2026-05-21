---
name: employee-role-self-service
version: 2.0.0
description: >
  Comprehensive rule for the Employee system role — covering role-mail login,
  self-service RBAC, ownership scoping, seeding, backend policies, frontend
  guards, and testing standards.
tags: [iam, rbac, employee, self-service, auth]
---

# Feature: Employee Role & Self-Service Login

Use this skill to implement, configure, test, and maintain the **Employee** system
role. This role enables rank-and-file employees to log in using a **role-scoped
email address** (`{position-handle}.{NN}@{tenant}.{com,...}`) and access only their own
self-service data — leaves, payslips, appraisals, and profile.

---

## 1. Core Concept — Role Mail Login

Each employee's login account is **automatically derived** from their position role, sequence, and
their configured tenant/email domain. This eliminates manual account creation and ensures a consistent,
predictable credential scheme.

### 1.1 Email Address Convention

The email address is constructed using the following parts:
`{position-handle}.{NN}@{tenant}.{com,...}`

| Variable | Rule | Example |
|---|---|---|
| `{position-handle}` | Lowercased role or position slug | `employee`, `frontend`, `backend` |
| `{NN}` | Two-digit sequential number (starting at `01`) | `01`, `02`, `03` |
| `{tenant}.{com,...}` | Configured tenant email domain / mail host | `gmail.com`, `acme.com` |
| **Final email** | `{position-handle}.{NN}@{tenant}.{com,...}` | `frontend.01@gmail.com`, `backend.01@gmail.com` |

> [!IMPORTANT]
> The `{tenant}.{com,...}` suffix represents the tenant's chosen email domain. It defaults to the tenant handle subdomain (e.g. `acme.com`), but can be mapped to any standard email domain like `gmail.com`.

### 1.2 Demo / Dev / QA Seed Account

A deterministic seed account is provisioned in **every** tenant database:

| Field | Value |
|---|---|
| **Email** | `employee.01@tanent.com` *(matches the dev tenant handle `tanent`)* |
| **Password** | `tt@126$Kh#` |
| **Role** | `Employee` (`slug: employee`) |
| **Employee ID** | `TT-0001` |
| **Employee Name** | `Base Employee` |

> [!NOTE]
> For local development, tests, and standard tenant seeds, the first employee account uses sequence `01` under the tenant handle (e.g. `employee.01@tanent.com`). Additional employees follow `employee.02@tanent.com`, `employee.03@tanent.com`, etc.

---

## 2. Role Definition & RBAC

### 2.1 System Role Metadata

```php
[
    'name'        => 'Employee',
    'slug'        => 'employee',
    'description' => 'Standard employee role with access to self-service portals.',
]
```

### 2.2 Permission Matrix (Least Privilege)

The `employee` role is granted **only** the following permission slugs:

| Permission Name | Slug | Module | Action | Scope |
|---|---|---|---|---|
| Read Leaves | `hrm.leave.read` | `hrm` | `read` | Own records only |
| Write Leaves | `hrm.leave.write` | `hrm` | `write` | Own records only |
| Read Appraisals | `hrm.performance.read` | `hrm` | `read` | Own records only |
| Read Payroll / Payslips | `hrm.payroll.read` | `hrm` | `read` | Own records only |
| Read Own Profile | `hrm.profile.read` | `hrm` | `read` | Own record only |
| Write Own Profile | `hrm.profile.write` | `hrm` | `write` | Own record only |

> [!IMPORTANT]
> **Explicitly excluded** — the Employee role MUST NOT have:
> - `hrm.employee.read` — would expose the full employee directory
> - `hrm.employee.write` — would allow editing other employees
> - Any `iam.*` permissions — no user/role/tenant administration
> - Any `*.delete` permissions — employees cannot hard- or soft-delete records

### 2.3 Adding New Permissions to the Employee Role

When a new self-service feature is added to the HRM module:
1. Define the new permission slug using `module.feature.action` in `TenantDatabaseSeeder.php`.
2. Add it to the `employee` role's `sync()` call in the same seeder.
3. Update the **Permission Matrix** table above.
4. Update the `employee_role.md` version badge.

---

## 3. Seeding (Backend — Laravel)

### 3.1 Canonical Seeder Pattern (`TenantDatabaseSeeder.php`)

The following is the authoritative pattern. **Do not deviate from this structure.**

```php
// 1. Upsert base permissions (idempotent)
Permission::updateOrCreate(['slug' => 'hrm.leave.read'],    ['name' => 'Read Leaves',          'module' => 'hrm', 'feature' => 'leave',       'action' => 'read']);
Permission::updateOrCreate(['slug' => 'hrm.leave.write'],   ['name' => 'Write Leaves',         'module' => 'hrm', 'feature' => 'leave',       'action' => 'write']);
Permission::updateOrCreate(['slug' => 'hrm.performance.read'], ['name' => 'Read Appraisals',  'module' => 'hrm', 'feature' => 'performance', 'action' => 'read']);
Permission::updateOrCreate(['slug' => 'hrm.payroll.read'],  ['name' => 'Read Payroll/Payslips','module' => 'hrm', 'feature' => 'payroll',     'action' => 'read']);
Permission::updateOrCreate(['slug' => 'hrm.profile.read'],  ['name' => 'Read Own Profile',     'module' => 'hrm', 'feature' => 'profile',     'action' => 'read']);
Permission::updateOrCreate(['slug' => 'hrm.profile.write'], ['name' => 'Write Own Profile',    'module' => 'hrm', 'feature' => 'profile',     'action' => 'write']);

// 2. Upsert the Employee role
$employeeRole = Role::updateOrCreate(['slug' => 'employee'], [
    'name'        => 'Employee',
    'description' => 'Standard employee role with access to self-service portals.',
]);

// 3. Sync permissions (replaces all; idempotent)
$employeeRole->permissions()->sync(
    Permission::whereIn('slug', [
        'hrm.leave.read',
        'hrm.leave.write',
        'hrm.performance.read',
        'hrm.payroll.read',
        'hrm.profile.read',
        'hrm.profile.write',
    ])->get()
);

// 4. Upsert the seed user account
$handle = tenant('id'); // e.g. "tanent" in dev, "acme" in production
$roleEmail = "employee.01@{$handle}.com";

$employeeUser = \App\Models\Tenant\User::firstOrCreate(
    ['email' => $roleEmail],
    [
        'name'      => 'Base Employee User',
        'password'  => \Illuminate\Support\Facades\Hash::make('password'),
        'is_active' => true,
    ]
);

if (!$employeeUser->roles->contains($employeeRole->id)) {
    $employeeUser->roles()->attach($employeeRole->id);
}

// 5. Upsert the linked Employee record
if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
    \App\Models\Tenant\Employee::firstOrCreate(
        ['email' => $roleEmail],
        [
            'employee_id' => 'TT-0001',
            'first_name'  => 'Base',
            'last_name'   => 'Employee',
            'user_id'     => $employeeUser->id,
            'status'      => 'active',
            'hired_at'    => now()->toDateString(),
        ]
    );
}
```

> [!NOTE]
> The dev seed email is hardcoded as `employee.01@tanent.com` in the current seeder
> for backward compatibility. For new tenants, use the dynamic `employee.01@{$handle}.com`
> pattern shown above.

---

## 4. Authentication Flow (Role Mail Login)

```mermaid
sequenceDiagram
    participant FE as Frontend (Nuxt)
    participant MW as Middleware (InitializeTenancyByHandle)
    participant AC as AuthController
    participant PP as Laravel Passport
    participant DB as Tenant DB

    FE->>MW: POST /api/v1/auth/login<br/>Header: X-Tenant-Handle: acme<br/>Body: {email: "employee.01@acme.com", password: "tt@126$Kh#"}
    MW->>DB: Switch to tenant DB (handle=acme)
    MW->>AC: Forward request
    AC->>DB: User::where('email', ...) scoped to tenant
    DB-->>AC: Return User + roles + permissions
    AC->>AC: Check is_active === true
    AC->>PP: Issue password_grant token (client_id=33)
    PP-->>AC: {access_token, refresh_token, expires_in}
    AC-->>FE: 200 {user: {..., roles:[employee]}, access_token, refresh_token}
    FE->>FE: Store tokens; redirect to self-service dashboard
```

### 4.1 Login Request Contract

```http
POST /api/v1/auth/login
X-Tenant-Handle: acme
Content-Type: application/json

{
  "email": "employee.01@acme.com",
  "password": "tt@126$Kh#"
}
```

### 4.2 Success Response Contract

```json
{
  "user": {
    "id": "<uuid>",
    "name": "Base Employee User",
    "email": "employee.01@acme.com",
    "is_active": true,
    "roles": [
      {
        "id": "<uuid>",
        "name": "Employee",
        "slug": "employee",
        "permissions": [
          { "slug": "hrm.leave.read" },
          { "slug": "hrm.leave.write" },
          { "slug": "hrm.performance.read" },
          { "slug": "hrm.payroll.read" },
          { "slug": "hrm.profile.read" },
          { "slug": "hrm.profile.write" }
        ]
      }
    ]
  },
  "token_type": "Bearer",
  "access_token": "<token>",
  "refresh_token": "<token>",
  "expires_in": 31536000
}
```

### 4.3 Error Responses

| HTTP | Cause | Body |
|---|---|---|
| `401` | Wrong email or password | `{"message": "Invalid credentials."}` |
| `403` | `is_active = false` | `{"message": "Account is inactive."}` |
| `422` | Missing email or password field | Laravel validation error |
| `500` | Passport client not configured | `{"message": "Passport password client is not configured."}` |

---

## 5. Ownership Scoping (Backend Policies)

Employees must **only** access their own data. This is enforced by Laravel Policies.

### 5.1 EmployeePolicy Pattern

```php
// app/Tenants/Modules/HRM/Policies/EmployeePolicy.php

public function view(User $user, Employee $employee): bool
{
    // Employee can only view their own profile
    return $user->employee?->id === $employee->id;
}

public function update(User $user, Employee $employee): bool
{
    return $user->employee?->id === $employee->id;
}

public function viewAny(User $user): bool
{
    // Block directory listing for the Employee role
    return $user->hasPermission('hrm.employee.read');
}
```

### 5.2 Route-Level Scoping

```php
// routes/tenant.php — self-service routes (no hrm.employee.read required)
Route::middleware(['auth:api', 'can:view,employee'])
    ->get('/employees/{employee}', [EmployeeController::class, 'show']);

// Admin routes (require hrm.employee.read)
Route::middleware(['auth:api', 'permission:hrm.employee.read'])
    ->get('/employees', [EmployeeController::class, 'index']);
```

### 5.3 Service-Level Enforcement

```php
// In EmployeeService::show(User $user, Employee $employee)
if ($user->employee?->id !== $employee->id && !$user->hasPermission('hrm.employee.read')) {
    throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden.');
}
```

---

## 6. Frontend Guards (Nuxt / PrimeVue)

### 6.1 Route Middleware

```typescript
// middleware/employee-only.ts
export default defineNuxtRouteMiddleware(() => {
    const { hasPermission, hasRole } = usePermissions()

    // Redirect non-employee users away from the self-service portal
    if (!hasRole('employee') && !hasPermission('hrm.leave.read')) {
        return navigateTo('/dashboard')
    }
})
```

### 6.2 Composable Pattern (`usePermissions`)

```typescript
// composables/usePermissions.ts
export function usePermissions() {
    const auth = useAuthStore()

    const hasPermission = (slug: string): boolean =>
        auth.user?.roles?.some(role =>
            role.permissions?.some(p => p.slug === slug)
        ) ?? false

    const hasRole = (slug: string): boolean =>
        auth.user?.roles?.some(r => r.slug === slug) ?? false

    const isEmployee = computed(() => hasRole('employee'))

    return { hasPermission, hasRole, isEmployee }
}
```

### 6.3 Template Guards

```vue
<!-- Show "Edit" only if employee owns the record -->
<Button
    v-if="isOwnProfile"
    label="Edit Profile"
    v-can="'hrm.profile.write'"
/>

<!-- Never show this to the Employee role -->
<Button
    v-if="!isEmployee"
    label="View All Employees"
    v-can="'hrm.employee.read'"
/>
```

### 6.4 Self-Service Sidebar Navigation

The sidebar MUST be filtered by the logged-in user's permissions. For the
`employee` role, only these items should be visible:

| Menu Item | Required Permission |
|---|---|
| My Profile | `hrm.profile.read` |
| My Leaves | `hrm.leave.read` |
| My Payslips | `hrm.payroll.read` |
| My Appraisals | `hrm.performance.read` |

---

## 7. Audit Logging

Every self-service action by an Employee MUST generate an entry in `audit_logs`:

| Action | `audit_logs.action` | `audit_logs.auditable_type` |
|---|---|---|
| Employee views own profile | `employee.profile.viewed` | `Employee` |
| Employee updates own profile | `employee.profile.updated` | `Employee` |
| Employee submits leave request | `employee.leave.submitted` | `Leave` |
| Employee cancels leave request | `employee.leave.cancelled` | `Leave` |
| Employee views payslip | `employee.payslip.viewed` | `Payslip` |

### Audit Entry Contract

```php
AuditLog::create([
    'actor_id'        => $user->id,
    'actor_handle'    => $user->email,
    'action'          => 'employee.leave.submitted',
    'auditable_type'  => Leave::class,
    'auditable_id'    => $leave->id,
    'old_values'      => null,
    'new_values'      => $leave->toArray(),
    'ip_address'      => request()->ip(),
    'user_agent'      => request()->userAgent(),
]);
```

---

## 8. Best Practices & Security Checklist

- ✅ **Least Privilege**: The `employee` role has exactly the minimum permissions needed; no extras.
- ✅ **Strict Tenancy**: All requests must carry `X-Tenant-Handle`; the middleware enforces DB isolation.
- ✅ **Ownership Policies**: Policies are on every HRM model; direct ID access is always validated.
- ✅ **Soft Deletes Only**: Never hard-delete a user or employee account; mark as `is_active = false`.
- ✅ **Idempotent Seeding**: `updateOrCreate` / `firstOrCreate` patterns prevent duplicate seeds.
- ✅ **Password Hashing**: Always `Hash::make()` — never store plain text.
- ✅ **Role Mail Consistency**: For any new tenant, derive the employee login email from the handle.
- ✅ **No Admin Panels**: The Employee role MUST be redirected away from any admin dashboard route.
- ✅ **Token Scoping**: The issued Passport token carries the `employee` role scope; backend always re-verifies.

---

## 9. Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| `401` on login with correct email | Employee user doesn't exist in **this** tenant's DB | Run `php artisan tenants:seed --tenants=<handle>` |
| `403` after valid login | `is_active` is `false` | Set `users.is_active = true` for the user |
| Employee sees admin routes | Frontend middleware `employee-only` not applied | Add middleware to the admin layout/route |
| Employee can view all employees | `EmployeePolicy::viewAny` not registered | Register policy in `AuthServiceProvider` |
| Wrong permissions returned | `roles.permissions` not eager-loaded | Ensure `load('roles.permissions')` is called in `UserResource` |
| Role mail email incorrect | Tenant handle mismatch | Verify `tenant('id')` returns the correct handle string |
