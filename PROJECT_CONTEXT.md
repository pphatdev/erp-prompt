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

Each module has `skills/{module}/skill.md` with a code map + scope summary + link to `.task/{module}/` (the authoritative status + scope source). `.task/task.md` is the master checklist.

| Module | Skill | Backend | Frontend | Notes |
| :--- | :--- | :---: | :---: | :--- |
| **IAM** | [`skills/iam/skill.md`](./skills/iam/skill.md) | ✅ | ✅ | Users, roles, permissions, workflow statuses, password reset. `hasPermission()` on User model. |
| **Configuration (Settings)** | [`skills/configuration/skill.md`](./skills/configuration/skill.md) | ✅ | ✅ | Branding, locale, notifications, security, numbering (7 prefixes), modules (admin-only), platform. |
| **Modules System** | [`skills/modules/skill.md`](./skills/modules/skill.md) | ✅ | ✅ | `modules` table (self-FK), `is_active` / `is_core` / parent / sort, `useModules` composable, sidebar gating. |
| **Dashboard** | n/a (`pages/dashboard.vue`) | ✅ | ✅ | `DashboardSummaryService`; KPI grid + 7-day revenue + headcount; admin vs customer branches. |
| **CRM** | [`skills/crm/skill.md`](./skills/crm/skill.md) | ✅ | ✅ | Leads, Opportunities (Kanban), Contacts, Activities (polymorphic), Appointments, B2B/B2C Product Schedule. |
| **Sales** | [`skills/sales/skill.md`](./skills/sales/skill.md) | ✅ | ✅ | Customers, Quotations, Orders, Invoices, Subscriptions, Tenant provisioning. Target-flow refactor planned ([`rules/hybrid_sales_business_flow.md`](./rules/hybrid_sales_business_flow.md)). |
| **HRM** | [`skills/hrm/skill.md`](./skills/hrm/skill.md) | ✅ | ✅ | Employees, departments, positions, leave, shifts, attendance, payroll, recruitment, appraisals, careers portal, work schedules, hierarchical HRM settings. |
| **Inventory** | [`skills/inventory/skill.md`](./skills/inventory/skill.md) | ✅ | ✅ | Products + variants, Categories tree, Warehouses, Suppliers, Stock movements, PO FSM, WAC, low-stock alerts, vendor/AP extension. FIFO + storefront still planned. |
| **FMS / Finance** | [`skills/fms/skill.md`](./skills/fms/skill.md) | ◐ | ◐ | Customer Receipts shipped (UI + service + GL). Estimates, AP/AR UI, Tax, Reports still planned. |
| **Accounting / GL** | [`skills/accounting/skill.md`](./skills/accounting/skill.md) | ✅ | ✅ | CoA, Journals + Ledger (immutable + reversible), Exchange Rates, Bank, AR (Receipts/CN/DN), AP (Bills/Pay/Reimbursement/Cash Advance/Expense), Budgets. Fiscal-period locks open. |
| **POS** | [`skills/pos/skill.md`](./skills/pos/skill.md) | ✅ | ✅ | Terminals, Shifts (cashier/terminal mutex + variance reconcile), Orders (checkout/void with idempotent `client_uuid`), thermal-receipt printout. Offline IndexedDB deferred. |
| **Ecommerce (B2C)** | [`skills/ecommerce/skill.md`](./skills/ecommerce/skill.md) | ✅ | ✅ | Storefront (catalog/cart/checkout/account), shopper Passport guard, admin orders+refunds+customers, FMS cash-receipt journal, webhook signing. Blocked on `INV-RESERVE` + `INV-STOREFRONT`. |
| **Calendar** | [`skills/calendar/skill.md`](./skills/calendar/skill.md) | ✅ | ✅ | Holidays + compensatory days + unified events (holidays/leaves/shifts/CRM/custom). Privacy masking. Attendance reconciler deferred. |
| **eApprovals** | [`skills/eapprovals/skill.md`](./skills/eapprovals/skill.md) | ✅ | ◐ | Workflows + levels + actions + notifications + escalation/delegation shipped. Forms request UI pending. |
| **eDocuments** | [`skills/edocuments/skill.md`](./skills/edocuments/skill.md) | ✅ | ✅ | Folders + documents + versions + shares + acknowledgements + public share viewer. P0 Pest pending. |
| **Documents (CMS)** | [`skills/documents/skill.md`](./skills/documents/skill.md) | ◐ | ❌ | CMS folders + check-in/out shipped. Explorer UI + concurrency modals pending. |
| **Projects** | [`skills/projects/skill.md`](./skills/projects/skill.md) | ✅ | ✅ | Projects + Tasks (list/board) + Kanban + Timesheets (hour cap + leave/period blocks). WBS/Gantt/dependencies deferred (schema gap). |
| **Fleet** | [`skills/fleet/skill.md`](./skills/fleet/skill.md) | ✅ | ✅ | Vehicles, fuel, maintenance pages shipped (camelCase resources, policies, monotonic mileage). Map overlay + signed-URL receipts + scheduler deferred. |
| **Assets** | [`skills/assets/skill.md`](./skills/assets/skill.md) | ✅ | ✅ | Tracking + QR + depreciation (SL/DDB/SYD) + revaluation + disposal + audits. FMS journals + HRM custodian. P0 Pest shipped. |
| **Reporting & Analytics** | [`skills/reporting/skill.md`](./skills/reporting/skill.md) | ◐ | ◐ | Dashboard infrastructure + `DashboardSummaryService` shipped. Configurable widget builder + scheduled reports planned. |

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
