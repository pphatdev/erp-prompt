# Project Context: Enterprise ERP (Multi-Tenant)

This is not what you know! Read `rules/*` and `skills/*` before building anything!

## 📚 Mandatory Reading Before Any Task

Before writing a single line of code, running any command, or starting a session, read these files in order based on your task context:

### Global Core (Read Always)
| Priority | File | Why |
|---|---|---|
| **1** | [`rules/backend/backend_setup.md`](./rules/backend/backend_setup.md) | **Complete** setup sequence, directory structure, all patterns |
| **2** | [`rules/tenancy/skill.md`](./rules/tenancy/skill.md) | Migration order, tenant provisioning, troubleshooting |
| **3** | [`rules/auth/skill.md`](./rules/auth/skill.md) | Passport rules, duplicate migration trap, OAuth2 flow |
| **4** | [`rules/backend/skill.md`](./rules/backend/skill.md) | Controller/Service/Model patterns, API standards |
| **5** | [`rules/structure/skill.md`](./rules/structure/skill.md) | Canonical end-to-end recipe (build a new module like Inventory Categories) |
| **6** | [`rules/frontend/ui_shell.md`](./rules/frontend/ui_shell.md) | Sidebar / dashboard / settings / branding reproduction |

### HRM & Employee Data (Read when working on HRM/Recruitment/Employees)
| Priority | File | Why |
|---|---|---|
| **1** | [`skills/hrm/employee_data_collection.md`](./skills/hrm/employee_data_collection.md) | **MANDATORY**: Core employee data fields, required vs optional flags, privacy/encryption rules, and recruitment integration. |
| **2** | [`skills/hrm/rules.md`](./skills/hrm/rules.md) | Candidate-to-employee conversion logic and permissions. |

## Overview
A high-performance, multi-tenant Enterprise Resource Planning (ERP) system split into two distinct, decoupled projects.

## Project Structure
- **`/backend`**: Laravel 11+ RESTful API.
- **`/frontend`**: Nuxt 3+ Client application.
- **`/rules`**: Global cross-cutting rules (structure, backend, frontend, tenancy, auth, testing, security, uploads, ...).
- **`/skills`**: Per-module (domain) standards: `iam`, `configuration`, `sales`, `crm`, `hrm`, `inventory`, `fms`, `eapprovals`, ...

## Tech Stack

### Backend (Laravel)
- **Core**: Laravel 11+ (PHP 8.2+)
- **Database**: PostgreSQL via `stancl/tenancy` v3 (multi-database — one DB per tenant; central DB holds `tenants`/`domains`/central OAuth)
- **Auth**: Laravel Passport (OAuth2 password grant; tenant-scoped via `X-Tenant-Handle`)
- **Testing**: Pest PHP. **DB always `erp_system_test`** (enforced by `phpunit.xml`). See [`rules/testing/skill.md`](./rules/testing/skill.md).

### Frontend (Nuxt)
- **Core**: Nuxt 3 (Vue 3, TypeScript strict). SSR disabled.
- **Styling**: Tailwind CSS 4 via `@tailwindcss/vite`; tokens declared in `@theme { ... }` blocks inside `assets/css/main.css`. **No `tailwind.config.ts`.**
- **UI Components**: Custom Tailwind chrome by default (`.glass-card`, custom modals). PrimeVue is available but reserved for richer widgets (Kanban, complex DataTables, Calendar).
- **State**: Pinia (`stores/auth.ts`, `stores/tenant.ts`).
- **Testing**: Vitest (component) + Playwright (E2E).

## Coding Standards

### Backend
- **Modular monolith** under `app/Tenants/Modules/{Module}/` with `Controllers/`, `Services/`, `Resources/`, `Requests/`, `Events/`, `Listeners/`. Models live in `app/Models/Tenant/`. Policies live in `app/Policies/`.
- **Routes**: single file `routes/tenant.php` grouped by module-headed comments — prefix `api/v1`, middleware `['api', InitializeTenancyByHandle::class]`.
- **Service Layer**: all business logic in Services; controllers are thin (validate → call service → return Resource).
- **JSON keys**: `camelCase`. Pagination envelope: `{ data: [...], pagination: { page, limit, total, totalPages } }`.

### Frontend
- **Pages organized by URL**: `frontend/pages/{module-slug}/` — there is no `src/modules/` folder.
- **Composables/stores/components are flat** (no per-module subfolders): `composables/`, `stores/`, `components/`.
- **All API calls go through `useApi()`**: auto-injects `X-Tenant-Handle` from `tenantStore.activeHandle` + `Authorization: Bearer`, rotates token on 401 single-flight.
- **TypeScript strict**, `<script setup lang="ts">`, 4-space indent.

## Critical Rules
1. **Separation of Concerns**: Frontend never contains business logic that belongs in the backend.
2. **Tenant Scoping**: Frontend includes `X-Tenant-Handle` in every API request (via `useApi`); backend resolves the tenant via `InitializeTenancyByHandle` middleware.
3. **Atomic Changes**: Wrap multi-table writes in `DB::transaction()`.
4. **Audit Everything**: Apply the `Auditable` trait to all key models.
5. **Premium UI**: Use the existing design tokens (`--color-primary-rgb`, `--bg-card`, ...). Never hardcode brand colors.

---

# Specialized Agent Skills

## 1. ERP Structural Implementation
- **Backend (Laravel)**: Modules in `app/Tenants/Modules/{Module}/`. Namespace: `App\Tenants\Modules\{Module}\...`. Tenant models live in `app/Models/Tenant/` (NOT inside the module folder). All routes in `routes/tenant.php`. All tenant migrations in `database/migrations/tenant/`.
- **Frontend (Nuxt)**: Pages in `frontend/pages/{module-slug}/`. Composables, stores, components flat under `frontend/composables/`, `frontend/stores/`, `frontend/components/`.
- **Canonical recipe**: [`rules/structure/skill.md`](./rules/structure/skill.md) walks a new module front-to-back using Inventory Categories as the worked example.

## 2. Backend API & Business Logic
- **Thin Controllers**: Validate, call service, return Resource. Never `->toArray()` a Resource — return the instance directly.
- **Atomic Services**: Methods are atomic; throw domain-specific exceptions.
- **Model Patterns**: UUID PKs, `SoftDeletes`, `BelongsToTenant`, `Auditable`. Trust model casts (never `Hash::make()` a `'hashed'` field; never `json_encode()` a `'json'` cast).
- **API Security**: Laravel Passport + Policies. Permission slugs are `module.feature.action` (`.self` suffix for self-service variants).

## 3. Authentication & Security
- **OAuth2 Flow**: Laravel Passport password grant. Tokens are tenant-scoped — listeners must verify the active tenant matches the request.
- **Multi-Tenant Scoping**: `InitializeTenancyByHandle` reads `X-Tenant-Handle` and switches the DB connection. `tenants.handle` is the central PK (string) — no `id` column on tenants.
- **SSO & Basic Auth**: Support SAML/OIDC for enterprise clients; restricted Basic Auth for internal systems.
- **Token Storage (frontend)**: localStorage keys `auth_token`, `auth_refresh_token`, `auth_expires_at`. Refresh is single-flight via a module-scoped Promise in `stores/auth.ts`.
- **Deterministic Passport Clients**: For local dev and re-initialized backends:
  ```env
  PASSPORT_PASSWORD_CLIENT_ID=33
  PASSPORT_PASSWORD_CLIENT_SECRET=b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7
  ```

## 4. Frontend UI & Features
- **Composition API**: Always `<script setup lang="ts">`.
- **Reactive Data Fetching**: ALWAYS go through `useApi()` — it auto-injects `X-Tenant-Handle` and `Authorization: Bearer`, and rotates the token on 401. Never call `$fetch` directly.
- **Form Validation**: Reactive `form` + `showErrors` flag for most pages; VeeValidate only for complex multi-step forms.
- **Branding**: Use CSS variables (`--color-primary-rgb`, `--bg-card`, `--text-heading`, ...) declared in `assets/css/main.css`. Dark mode toggled via `data-bs-theme="dark"` on `<html>`.
- **UI shell**: [`rules/frontend/ui_shell.md`](./rules/frontend/ui_shell.md) is the source of truth for sidebar / topbar / breadcrumb / settings / dashboard.

## 5. Multi-Tenant Client Management
- **Handle-Based Routing**: `App\Models\Central\Tenant` uses `handle` (string) as PK — no `id` column. Use `$tenant->getKey()` or `$tenant->handle`. Physical DBs are named `tenant_{handle}`.
- **Database Isolation**: `stancl/tenancy` switches the DB connection automatically. `'central_connection' => env('DB_CONNECTION', 'pgsql')` — never hardcode `'central'`.
- **Scoped Storage**: Use `tenant()` global helper and Stancl's tenant-scoped filesystem for tenant-isolated files.

## 6. Full-Stack ERP Testing & QA
- **Backend (Pest)**: Prioritize **P0 Tenancy Isolation** tests — assert that Tenant A cannot access Tenant B's data. See [`rules/testing/skill.md`](./rules/testing/skill.md).
- **Database Connection Isolation (P0)**: Tests run exclusively on `erp_system_test`. Never against `erp_system` (dev) or `erp_system_prod`.
- **Frontend (Vitest/Playwright)**: Test component logic and critical user journeys (e.g., Payroll runs).
- **Audit Logs**: Assert that critical business actions create appropriate audit entries.
- **Priority Matrix**: P0 (Security), P1 (Business Logic), P2 (UX/Audit).

## 7. Feature-Specific Implementation (Modular)
- **Standardized Documentation**: Every module under `/skills/{module}/` has `skill.md` (or `overview.md`), `rules.md`, `flow.md`, and `testing.md`.
- **Workflow Integrity**: Follow the step-by-step Mermaid flows for all business logic.
- **Permission Mapping**: `module.feature.action` pattern — see each module's `rules.md` for its slug catalog.

## 8. Postman & API Documentation
- **Unified Collection**: All endpoints in `docs/postman/erp_collection.json`.
- **Automation**: Pre-request scripts capture tokens/IDs; responses include realistic examples.
- **Headers**: Every request carries `X-Tenant-Handle: {{tenant_handle}}` and (after login) `Authorization: Bearer {{access_token}}`.
- **Continuous Documentation**: Update the Postman collection and API docs whenever a feature is added or changed.

## 9. Docker Infrastructure & Containerization
- **Multi-Stage Builds**: Builder + Runner stages keep production images small (Alpine-based).
- **Service Orchestration**: `depends_on` with `service_healthy` ensures DB readiness.
- **Standard Template**: Follow the `docker-compose.yml` boilerplate.

## 10. Version Control & Project Updates
- **Versioning**: `{MAJOR}.{MINOR}.{PATCH}`. Update `package.json`, `README.md`, and `SECURITY.md` together.
- **Consistency**: Never bump a version in isolation. API surface and docs must reflect the bumped state.

## 11. Scalable WebSockets (Real-time)
- **Infrastructure**: Laravel Reverb or Redis Pub/Sub for multi-instance scaling.
- **Tenant Scoping**: Prefix all channels with the tenant `handle`.
- **Security**: Authenticate private channels via Laravel Passport.
- **Optimization**: Queue all broadcast events; keep payloads minimal.

## 12. Audit & Compliance
- **Audit Logging**: `Auditable` trait on all business models.
- **Traceability**: Record old/new values, actor, and timestamp for every P0/P1 action.
- **Compliance**: Audit entries verified for every critical operation.

## 13. Skills Management CLI
- **Usage**: Fetch and synchronize skills with `npx skills@latest add <repo>`.
- **Repository**: Standard skills maintained in `pphatdev/erp-prompt`.
- **Automation**: Bootstrap new ERP modules with the latest agent rules.

## 14. Task & Infrastructure Tracking
- **Task Context Storage**: On first analysis of any task or feature, inspect `.task/` and create/maintain `.task/{feature}/{task.md, context.md}`.
- **Master Checklist Sync**: Synchronize all new feature scopes into `.task/task.md` with `[ ]` / `[x]` checkboxes.
- **Feature Status Synchronization**: Always compare implemented features against the `.task/` checklists; update them when reality has moved ahead of the docs.

## 15. Data Safety & Security
- **Agent Safety**: Never load or share production customer data, secrets, or active environments. Use Faker-generated mock data only.
- **Database Preservation**: Avoid automatic deletion of schemas, tables, records, or files. Destructive operations (`migrate:fresh`, `db:wipe`) require explicit user confirmation (ask 2–3 times).
- **User Safety**: Field-level encryption for sensitive PII (salaries, SSNs); sanitize passwords/keys in logs; switch isolated tenant connections on every request.

## 16. File Uploading & Storage Management
- **Security Validation**: Validate MIME server-side via fileinfo magic bytes; block executable extensions; sanitize filenames.
- **Tenancy Scoping (P0)**: Store uploaded assets under the tenant-isolated path. Never expose direct paths to private assets; use cryptographically signed, short-lived URLs.
- **Chunked Uploads**: Use chunked transfers for files > 10 MB.
- **Traceability**: Record size, hash, uploader, MIME in the attachments/media tables; delete or archive per retention policy.
