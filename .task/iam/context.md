# Feature Context: Identity & Access Management (IAM)

This document defines the implementation phases for the IAM module, ensuring compliance with the project's multi-tenant and security standards.

## Implementation Phases

### Phase 1: Tenancy & Core Auth (Backend)
- [x] Initialize Laravel 11 project in `/backend`.
- [x] Install and configure `stancl/tenancy` for multi-database isolation.
- [x] Install Laravel Passport for OAuth2 authentication.
- [x] Create `Tenant` model and central database migrations.
- [x] Implement `X-Tenant-Handle` middleware for tenant identification.

### Phase 2: User & RBAC Schema (Backend)
- [x] Create tenant-scoped migrations for `users`, `roles`, and `permissions`.
- [x] Implement `module.feature.action` permission pattern.
- [x] Add `Auditable` trait to User and Role models.
- [x] Implement `RoleService` and `UserService` in the tenant module namespace.

### Phase 3: Auth API & MFA (Backend)
- [x] Implement Sign-In/Sign-Out endpoints with Passport.
- [x] Add OTP/MFA logic for administrative roles.
- [x] Create `JsonResource` transformers for IAM entities.
- [x] Implement Authorization Policies for all IAM routes.

### Phase 4: Frontend Scaffolding (Frontend)
- [x] Initialize Nuxt 3 project in `/frontend`.
- [x] Configure Tailwind CSS 4 and PrimeVue.
- [x] Create `useApi` and `useAuth` composables.
- [x] Setup Pinia stores for user profile and tenant context.

### Phase 5: IAM Dashboard & Components (Frontend)
- [x] Build Tenant Management interface.
- [x] Implement User Management with Role assignment.
- [x] Create Permission Matrix UI.
- [x] Apply "Premium Design" tokens (Glassmorphism, Dark Mode).

### Phase 6: Security Audit & Testing (Full-Stack)
- [x] P0 Tenancy Isolation tests (Ensure Tenant A cannot see Tenant B's users).
- [x] RBAC verification tests.
- [x] Audit log generation checks.
- [x] MFA flow validation.
