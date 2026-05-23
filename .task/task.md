# ERP Master Progress Registry

> Last synced: 2026-05-23

## Infrastructure & Platform
- [x] Laravel multi-tenant backend (stancl/tenancy v3, multi-database)
- [x] Nuxt 3 frontend with Tailwind CSS 4 + PrimeVue
- [x] Laravel Passport OAuth2 (password grant, deterministic client IDs)
- [x] `useApi` composable — auto `X-Tenant-Handle` + token rotation
- [x] PostgreSQL self-referential FK fix (split Schema::create + Schema::table)

## IAM (Identity & Access Management)
- [x] Users CRUD (`/users`)
- [x] Roles & permissions matrix — DB-backed, 4 tables
- [x] `module.feature.action` permission slugs
- [x] `hasPermission()` on User model (bypasses Gate)
- [x] `settings.read` / `settings.write` permissions seeded
- [x] `SettingsPermissionSeeder` for existing-tenant backfill

## Modules System
- [x] `modules` table (self-FK, `is_active`, `is_core`, `parent_id`, `sort_order`, `group`)
- [x] `product_modules` pivot (software products linked to system modules)
- [x] `ModuleController` — index, allForManagement, toggle, slugs, syncProduct
- [x] `useModules` composable — singleton, fail-open, `hasModule()`
- [x] Sidebar module gating (`moduleSlug` on nav items)
- [x] Entitlement cascade — `expandEntitledSlugs()` propagates parent to children on provisioning
- [x] Settings Modules tab (adminOnly, tree view, toggle, core protected)
- [x] `ModuleSeeder` — static menu items seeded to DB

## Sidebar / Layout
- [x] `default.vue` layout — compact rail, flyout hover, mobile drawer
- [x] Permission-gated nav items (OR semantics for arrays)
- [x] Loading skeleton (`skeletonGroups`, `nav-skeleton` shimmer CSS)
- [x] Breadcrumb system with entity-name override

## Settings / Branding
- [x] `GET /settings`, `PUT /settings` (key/value store)
- [x] `UpdateSettingsRequest::authorize()` — uses `hasPermission()` not Gate
- [x] Customer admin can update branding (logo, colors, theme)
- [x] Theme applied immediately on save + persisted in localStorage
- [x] Settings tabs: branding, locale, notifications, security, modules (adminOnly), platform (adminOnly)

## Dashboard
- [x] `GET /api/v1/dashboard/summary` — `DashboardSummaryController` + `DashboardSummaryService`
- [x] KPIs: employees, leave, attendance, sales, inventory, projects, finance
- [x] Charts: 7-day revenue trend + headcount by department
- [x] Recent: last 5 orders + last 5 pending leaves
- [x] `useDashboard` composable — singleton, 5-min staleness, `revenueBars`, `headcountBars`
- [x] Admin dashboard: full KPI grid + charts + tables
- [x] Customer dashboard: module-gated sections (hrm / sales)
- [x] Shimmer skeletons + error state + refresh button

## Sales / CRM
- [x] Customers CRUD + handle check
- [x] Leads CRUD + win action
- [x] Quotations (index, store, show, destroy + items)
- [x] Orders (CRUD + fulfillment flow)
- [x] Invoices (CRUD + confirm/cancel)
- [x] Subscriptions (CRUD + activate/cancel)
- [x] `TenantProvisioningService` — provision DB on subscription creation
- [x] Software products linked to system modules (modal picker, badge display)

## HRM
- [x] Employees CRUD
- [x] Departments, Positions
- [x] Leave Requests + Leave Types
- [x] Shifts, Attendance (clock in/out), Overtime
- [x] Payroll Periods + Payslips
- [x] Vacancies, Applications, Candidates (Kanban)
- [x] Appraisals
- [x] Public careers portal (no-auth)

## Inventory
- [x] Products CRUD (with variants, module linking for software type)
- [x] Stock Movements
- [ ] Warehouse management UI
- [ ] Supplier management UI
- [ ] Purchase Orders UI

## FMS (Financial Management)
- [x] Chart of Accounts
- [x] Journal Entries + Ledger
- [ ] AP/AR module UI
- [ ] Tax management UI
- [ ] Financial reports UI

## Projects
- [x] Projects CRUD (backend)
- [x] Tasks CRUD + status update
- [x] Timesheets
- [ ] Project dashboard UI page
- [ ] Kanban board UI

## Assets
- [ ] Asset CRUD UI
- [ ] Depreciation UI

## Fleet
- [ ] Vehicle management UI
- [ ] Fuel logs UI
- [ ] Maintenance logs UI

## eApprovals
- [x] Approval workflows + levels (backend)
- [x] Approval actions (backend)
- [ ] eApprovals UI page

## eDocuments / Documents
- [x] CMS Folders + Documents (backend)
- [x] Document check-in/check-out (backend)
- [ ] Document explorer UI

## Reporting & Analytics
- [x] Dashboard/Widget CRUD infrastructure
- [x] `DashboardSummaryService` with real aggregation queries
- [ ] Configurable widget dashboard builder UI
- [ ] Scheduled reports UI
- [ ] Export to PDF/Excel

## Testing
- [ ] Pest tenancy isolation tests (P0)
- [ ] Vitest component tests
- [ ] Playwright E2E for critical flows
