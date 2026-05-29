# Feature: Identity & Access Management (IAM)

## Overview
IAM is the core security layer — every other module gates on permissions seeded here. It owns users, roles, permissions, workflow statuses, Passport authentication, and the password-reset flow.

## Implementation status

| Subsystem | Status | Notes |
|---|---|---|
| Users CRUD (`/users`) | ✅ Shipped | UUID PK; soft delete; `'hashed'` password cast; `BelongsToTenant + Auditable + SoftDeletes` |
| Roles + permissions matrix | ✅ Shipped | 4 tables: `roles`, `permissions`, `role_has_permissions`, `user_has_roles` |
| `module.feature.action` slug grammar | ✅ Shipped | `.self` suffix for self-service variants |
| `hasPermission(slug)` on User model | ✅ Shipped | Bypasses Laravel Gate — used directly in FormRequests for cross-cutting endpoints like `/settings` |
| `settings.read` / `settings.write` seeded | ✅ Shipped | `SettingsPermissionSeeder` (idempotent backfill for existing tenants) |
| Workflow statuses CRUD | ✅ Shipped | Generic `workflow_statuses` table consumed by HRM / eApprovals / Sales lifecycle |
| Password reset (`POST /users/{user}/reset-password`) | ✅ Shipped | Route registered **before** `apiResource` so it isn't swallowed by `{user}` matching |
| Frontend users page (`/settings/users`) | ✅ Shipped | Card grid + search + status filter; inline `useApi()` calls |
| Frontend roles page (`/settings/roles`) | ✅ Shipped | Matrix view; permission toggles |
| Self-service employee role | ✅ Shipped | See [`employee_role.md`](./employee_role.md), [`_flow.md`](./employee_role_flow.md), [`_testing.md`](./employee_role_testing.md) |
| Multi-tenant scoping enforced | ✅ Shipped | Every model uses `BelongsToTenant`; `InitializeTenancyByHandle` switches DB connection per request |
| Audit logging | ✅ Shipped | `Auditable` trait on User + role pivots — writes deltas to log channel |
| MFA / OTP | ❌ Planned | OTP scaffold exists in `rules/auth/skill.md`; UI + service not yet wired |
| SSO (SAML / OIDC) | ❌ Planned | `laravel/socialite` not installed |
| Audit log retention policy | ❌ Planned | Currently logs to default channel; no archive job yet |

## 1. Governance & Multi-Tenancy
- **Database Isolation**: Multi-database strategy via `stancl/tenancy`. Tenant PK is `handle` (string), no `id`.
- **Tenant Onboarding**: Automated provisioning via `TenantProvisioningService` (lives in Sales module — triggered by Sales-side subscription confirm).
- **Custom Branding**: Tenant-specific themes, logos applied via `tenantStore.applyBrandToDocument()` reading from `/settings/public`.

## 2. Role-Based Access Control (RBAC)
- **Role Management**: Define roles per tenant (admin, manager, finance, employee, ...). Slugs are seeded; admin gets every permission.
- **Permission Mapping**: `module.feature.action` (e.g. `sales.invoice.delete`).
- **Self-Service variants**: `.self` suffix paired with ownership check in the Policy.
- **Frontend gate**: `authStore.hasPermission(slug)` — super-admins short-circuit to `true`.

## 3. Identity & Security
- **Passport password grant** (deterministic dev client ID `33` per `.env.example`).
- **Refresh rotation**: single-flight via a module-scoped Promise in `stores/auth.ts` to prevent double-spend of the single-use refresh token.
- **MFA / OTP**: planned; secure random 6-digit, Redis-backed with TTL.
- **SSO**: planned via Socialite (OIDC / SAML).
- **Audit Logs**: every role/permission change + login/logout fires the `Auditable` trait.

## Read next
- [`skill.md`](./skill.md) — operational reference (routes, services, files, troubleshooting)
- [`rules.md`](./rules.md) — full RBAC spec
- [`flow.md`](./flow.md) — login + permission-check Mermaid flow
- [`employee_role.md`](./employee_role.md) — self-service role
- [`rules/auth/skill.md`](../../rules/auth/skill.md) — Passport setup details
