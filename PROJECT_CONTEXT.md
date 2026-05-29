# Enterprise ERP Project Context

## Project Overview
High-performance, multi-tenant Enterprise Resource Planning (ERP) system. Split into a Laravel multi-tenant API and a Nuxt 3 client application.

## Core Architecture
- **Multi-Tenancy**: `stancl/tenancy` multi-database — one PostgreSQL DB per tenant; central DB holds `tenants`, `domains`, and central OAuth tables. Tenant PK is `handle` (string), not UUID.
- **Backend**: Laravel 11+ (PHP 8.2+), Passport (OAuth2 password grant), Pest.
- **Frontend**: Nuxt 3 (SSR off), Vue 3 + TypeScript strict, Tailwind 4 (`@tailwindcss/vite` + `@theme` in `main.css` — no `tailwind.config.ts`), PrimeVue available but custom Tailwind chrome is the default, Pinia.
- **Agent system**: Rules in `/rules`, per-module skills in `/skills`.

## Project Structure
- `/backend`: Laravel API source.
- `/frontend`: Nuxt client source.
- `/skills`: Per-module standards (IAM, configuration, sales, crm, hrm, inventory, fms, ...).
- `/rules`: Cross-cutting rules (structure, backend, frontend, tenancy, auth, testing, security, uploads).
- `/tools`: Internal CLI tools (e.g. `skills-cli`).
- `/.task`: Per-feature progress trackers and the master checklist (`.task/task.md`).

## Critical Coding Standards

### 1. Multi-Tenant Isolation (P0)
- **Backend**: Models use the `BelongsToTenant` trait (from `App\Models\Traits\BelongsToTenant`). All tenant migrations go in `database/migrations/tenant/`.
- **Frontend**: Every API request carries `X-Tenant-Handle` — injected automatically by `useApi()`. Never call `$fetch` directly.

### 2. Business Logic (P1)
- **Service Layer**: Controllers stay thin (validate → call service → return Resource). All business logic lives in `app/Tenants/Modules/{Module}/Services/`.
- **Atomic Operations**: Wrap multi-table writes in `DB::transaction()`.
- **Audit Logging**: Apply the `Auditable` trait to all key models.
- **Trust model casts**: `password` is `'hashed'` cast — pass plaintext, never `Hash::make()` it. `Setting.value` is `'json'` cast — pass arrays/scalars, never `json_encode()`.

### 3. UI/UX (P2)
- **Premium Design**: Use the CSS-variable tokens from `assets/css/main.css` (`--color-primary-rgb`, `--bg-card`, `--text-heading`, ...). The default card chrome is `.glass-card`, not PrimeVue Card.
- **Responsive**: Mobile-first; layout uses a rail sidebar (260 → 70 px collapse, hover-flyout) and mobile drawer below `md`.

## Active Modules & Implementation Status

Every module has `skill.md` (or `overview.md`), `rules.md`, `flow.md`, and `testing.md` under `skills/{module}/`. Status reflects what's actually shipped (see `.task/task.md` for the authoritative master checklist).

| Module | Location | Backend | Frontend | Notes |
| :--- | :--- | :---: | :---: | :--- |
| **IAM** | `/skills/iam` | ✅ | ✅ | Users, roles, permissions, workflow statuses, password reset. `hasPermission()` on User model. |
| **Configuration / Settings** | `/skills/configuration` | ✅ | ✅ | Branding, locale, notifications, security, numbering (7 prefixes), modules (admin-only), platform. |
| **Modules System** | n/a (cross-cutting) | ✅ | ✅ | `modules` table (self-FK), `is_active` / `is_core` / parent / sort, `useModules` composable, sidebar gating. |
| **Dashboard** | n/a (`pages/dashboard.vue`) | ✅ | ✅ | `DashboardSummaryService`; KPI grid + 7-day revenue + headcount; admin vs customer branches. |
| **CRM** | `/skills/crm` | ✅ | ✅ | Leads, Opportunities (Kanban), Contacts, Activities (polymorphic), Appointments, B2B/B2C Product Schedule. |
| **Sales** | `/skills/sales` | ✅ | ✅ | Customers, Quotations, Orders, Invoices, Subscriptions, Tenant provisioning. Target-flow refactor planned. |
| **HRM** | `/skills/hrm` | ✅ | ✅ | Employees, departments, positions, leave, shifts, attendance, payroll, recruitment (vacancies → applications → candidates Kanban), appraisals, public careers portal. |
| **Inventory** | `/skills/inventory` | ✅ | ✅ | Products + variants, Categories (tree), Warehouses, Suppliers, Stock movements, Purchase Orders (full FSM), WAC, Low-stock alerts. FIFO + eCommerce sync still planned. |
| **FMS / Finance** | `/skills/fms` | ◐ | ◐ | Chart of Accounts, Journal Entries, Ledger shipped. Payments, Estimates, AP/AR UI, Tax, Reports still planned. |
| **eApprovals** | `/skills/eapprovals` | ✅ | ❌ | Workflows + levels + actions + notifications shipped. UI planned. |
| **eDocuments / Documents** | `/skills/edocuments`, `/skills/documents` | ✅ | ❌ | CMS folders + documents + check-in/out shipped. Explorer UI planned. |
| **Projects** | `/skills/projects` | ✅ | ❌ | Projects + tasks + timesheets shipped. UI planned. |
| **Reporting & Analytics** | `/skills/reporting` | ◐ | ◐ | Dashboard infrastructure + `DashboardSummaryService` shipped. Configurable widget builder + scheduled reports planned. |
| **Fleet** | `/skills/fleet` | ❌ | ❌ | Not yet implemented. |
| **Assets** | `/skills/assets` | ❌ | ❌ | Not yet implemented. |

Legend: ✅ Shipped · ◐ Partially shipped · ❌ Not started

## Testing & QA
- **Backend**: Pest PHP. Tests under `tests/Feature/Tenant/{Module}/`. **Always against `erp_system_test`** — enforced by `phpunit.xml`. Prioritize P0 Tenancy Isolation cases.
- **Frontend**: Vitest for components; Playwright for critical E2E (login, payroll run, order confirm, tenant provisioning).

## Documentation References
- [Build a New Module (recipe)](./rules/structure/skill.md)
- [Backend setup + directory map](./rules/backend/backend_setup.md)
- [UI shell reproduction](./rules/frontend/ui_shell.md)
- [Tenancy / migration order](./rules/tenancy/skill.md)
- [Auth / Passport / hashed-cast trap](./rules/auth/skill.md)
- [Agent standards](./rules/agent/SKILL.md)
- [Security Policy](./SECURITY.md)
