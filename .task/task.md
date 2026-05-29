# ERP Master Progress Registry

> Last synced: 2026-05-29

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
- [x] Settings tabs: branding, locale, notifications, security, **numbering**, modules (adminOnly), platform (adminOnly)
- [x] `Setting` model `value` cast fixed: `'array'` → `'json'` (scalar types round-trip correctly)

## Document Numbering Prefixes
> Full task: [`.task/numbering/task.md`](./.task/numbering/task.md) | Rule: [`skills/configuration/numbering.md`](./skills/configuration/numbering.md)
- [x] 7 prefix keys registered in `SettingService::defaults()` (employee, candidate, quotation, order, invoice, subscription, PO)
- [x] All generators read from `SettingService` with `empty()` fallback — no hardcoded values
- [x] All 7 code columns carry unique DB constraints
- [x] `TenantDatabaseSeeder` uses `generateNextEmployeeId()` — hardcoded `'TT-0001'` removed
- [x] Frontend Numbering tab — all 7 inputs, maxlength=16, live previews, immutability callout
- [ ] Collision retry on `23505` for sequential generators (Employee, Candidate) — **P1 open**
- [ ] `UpdateSettingsRequest` validation for `numbering.*` prefix format/length — **P1 open**
- [ ] Pest tests: prefix respected, fallback, tenancy isolation, audit log — **P1 open**
- [ ] Postman collection — `GET /settings?group=numbering` + `PUT /settings` prefix update examples

## Dashboard
- [x] `GET /api/v1/dashboard/summary` — `DashboardSummaryController` + `DashboardSummaryService`
- [x] KPIs: employees, leave, attendance, sales, inventory, projects, finance
- [x] Charts: 7-day revenue trend + headcount by department
- [x] Recent: last 5 orders + last 5 pending leaves
- [x] `useDashboard` composable — singleton, 5-min staleness, `revenueBars`, `headcountBars`
- [x] Admin dashboard: full KPI grid + charts + tables
- [x] Customer dashboard: module-gated sections (hrm / sales)
- [x] Shimmer skeletons + error state + refresh button

## Sales (O2C Billing)
- [x] Customers CRUD + handle check
- [x] Quotations (index, store, show, destroy + items)
- [x] Orders (CRUD + fulfillment flow)
- [x] Invoices (CRUD + confirm/cancel)
- [x] Subscriptions (CRUD + activate/cancel)
- [x] `TenantProvisioningService` — provision DB on subscription creation
- [x] Software products linked to system modules (modal picker, badge display)
- [ ] **Target-flow refactor (Planned)** — status enums to `draft`/`won`/`lost` (Quotation), `draft`/`confirm`/`cancel` (Order), `active`/`expired`/`cancelled` (Subscription); Lead→Customer conversion at Quotation `won`; tenant provisioning moved to Order `confirm`; Customer Account dashboard with countdown + renew/upgrade/downgrade/cancel. See `.task/sales/task.md` § Phase 9 and `rules/hybrid_sales_business_flow.md`.

## CRM (Customer Relationship Management)
- [x] Decouple Lead models and CrmService to dedicated namespace
- [x] Migrate Opportunities table & models (stages FSM)
- [x] Migrate Contacts table & models (encrypted phone/email)
- [x] Migrate Polymorphic Activities table & models (interaction logging)
- [x] Lead Qualification & Conversion transactional engine
- [x] API Surface (slim controllers, direct resource returns)
- [x] CRM permissions (`crm.leads.*` / `crm.opportunities.*` / `crm.contacts.*` / `crm.activities.*`) wired via 4 policies + `CrmPermissionSeeder`
- [x] PrimeVue Opportunity Kanban Board UI (drag-and-drop, loss reason guard)
- [x] Polymorphic Interaction Timeline UI component
- [x] Integration test suite under `tests/Feature/Tenant/Crm/` (OpportunityPipeline, LeadQualification, TenancyIsolation, PolymorphicActivity)
- [x] **B2B/B2C Product Schedule** — `opportunity_product_schedules` entity + service + REST CRUD; lock on terminal stage; snapshot to Quotation on `won`. (Phase 7 shipped backend + frontend editor.)
- [x] **`LeadQualified` handoff event** — replaced `OpportunityWon`+auto-Quotation listener; Customer creation deferred to Sales-side Quotation `win`.
- [x] **Appointments / Schedules calendar** — `crm_appointments` entity + service + REST + `crm.appointments.*` perms + policy + `pages/crm/schedules.vue` (agenda/week/month). See `.task/crm/task.md` § Phase 8.

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
- [x] **Product Variants CRUD** — `ProductVariantController` (nested under products + shallow), `ProductVariantService` with cross-table SKU uniqueness, `ProductVariantPolicy` reusing `inventory.product.*` perms. Products page modal gains a dynamic variants editor (add/remove rows, JSON attributes, persisted in a second round-trip after product save).
- [x] **Categories** — hierarchical taxonomy (`categories` table with self-FK parent_id), `Category` model + service (move-under-descendant cycle guard, archive blocked by children/products), `CategoryController` (flat + `?tree=1`), `CategoryPolicy` with `inventory.category.{read,write,delete}`. `/inventory/categories` page with tree view + color chips + parent picker. Category column/filter on the Products page; nullable `category_id` FK added to products.
- [x] Stock Movements ledger & service (recordMovement, transferStock)
- [x] Warehouse management API & PrimeVue UI (`/inventory/warehouses` — CRUD + KPI cards, archive blocked by on-hand stock guard)
- [x] Supplier directory API & PrimeVue UI (`/inventory/suppliers` — CRUD + rating/lead-time/terms, archive blocked by open PO guard)
- [x] Purchase Orders & P2P system (PR -> PO -> eApprovals -> GRN) — full FSM (`draft→submitted→approved→receiving→received`) with `/inventory/purchase-orders` list + `create` wizard + `[id]` detail/receive page. eApprovals routing in `ProcurementService::submit`.
- [x] Cost valuation integration (Weighted Average Costing inline on receive — see Phase 2 INV-WAC)
- [x] Low-Stock Alert & automated reorder suggestions engine (INV-LOWSTOCK backend completed)
- [ ] FIFO costing option (WAC shipped; FIFO still pending)
- [/] eCommerce Sync & stock reservation engine (15-min TTL locks + returns restock) — backend INV-RESERVE + INV-DAEMON shipped, INV-STOREFRONT pending
- [ ] Omnichannel price integration (Catalog pricing SSOT for Quotations, CRM, PO, eCommerce, POS)




## FMS / Finance
> Sidebar "Finance" group includes mirrored Invoices/Subscriptions from Sales (code stays under `App\Tenants\Modules\Sales\*`).
- [x] Chart of Accounts
- [x] Journal Entries + Ledger
- [ ] **Payments (Planned)** — record customer remittance, partial-apply against Invoice, post `DR Cash, CR AR`. See `.task/fms/task.md` § Phase 1.
- [ ] **Estimates (Planned)** — informal pre-binding pricing; convert to a Sales Quotation. See `.task/fms/task.md` § Phase 2.
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
> Full task: [`.task/fleet/task.md`](./.task/fleet/task.md) | Rule: [`skills/fleet/rules.md`](./skills/fleet/rules.md)
- [x] Backend API alignment (camelCase resources, policies, permissions, auditable, monotonic mileage)
- [ ] Vehicle management UI & Interactive map overlays
- [ ] Fuel logs UI & Tenant-isolated uploads
- [ ] Maintenance logs UI & threshold scheduler

## eApprovals
> Full task: [`.task/eapprovals/task.md`](./.task/eapprovals/task.md) | Rule: [`skills/eapprovals/rules.md`](./skills/eapprovals/rules.md)
- [x] Approval workflows + levels (backend)
- [x] Approval actions (backend)
- [x] IAM Permissions seeded (`approvals.*`)
- [x] Notifications, Escalation, and Delegation logic (backend)
- [ ] Pest tenancy isolation tests (P0) & State machine integrity (P1)
- [ ] eApprovals UI page

## eDocuments (Explorer)
> Full task: [`.task/edocuments/task.md`](./.task/edocuments/task.md) | Rule: [`skills/edocuments/rules.md`](./skills/edocuments/rules.md)
- [x] Backend scaffolding (Folder, Document, Tag models; migrations; service with banned-ext/MIME guards; index/store/show/download controllers)
- [x] Schema completion — `document_shares`, `document_acknowledgements`, `document_versions` migrations + `Tag` Auditable/SoftDeletes
- [x] Service layer — DocumentService update/delete/move/createVersion; FolderService (cycle-guarded move + recursive force delete); ShareLinkService (410/403/429); AcknowledgementService
- [x] Search & metadata — tag CRUD, filter by folder/tag/uploader/MIME/date/polymorphic, folder pagination
- [x] API surface — camelCase resources (Document, Folder, Tag, Share, Version, Ack); FormRequests; update/destroy/move endpoints on Document & Folder; public share routes
- [x] Access control — `EDocsPermissionSeeder` + `DocumentPolicy`/`FolderPolicy`/`DocumentTagPolicy` registered in `TenantServiceProvider` + `$this->authorize` on every action
- [x] Frontend Explorer MVP — `pages/edocuments/index.vue` (folders grid + docs table + kebab actions + upload/preview/share/rename modals), `pages/share/[token].vue` public viewer, `composables/useEDocuments.ts`, `stores/edocuments.ts`, sidebar wired
- [ ] QA — Pest isolation (P0), share-link expiry (P0), upload guards, search scoping, acknowledgement, permissions, resource contract

## Documents (CMS)
> Full task: [`.task/documents/task.md`](./.task/documents/task.md) | Rule: [`skills/documents/rules.md`](./skills/documents/rules.md)
- [x] CMS Folders + Documents (backend scaffolding completed)
- [x] Document check-in/check-out locking service operations (completed)
- [ ] Backend API alignment (camelCase resources, policies, permissions seeder)
- [ ] Document explorer UI (folders, files, breadcrumbs, search)
- [ ] Concurrency check-in/out visual flow & modal integrations
- [ ] Dynamic PDF / Image previewer modals

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

## Grid Cards Redesign
> Full task: [`.task/card_redesign/task.md`](./.task/card_redesign/task.md)
- [x] Redesign 13 frontend pages containing grid cards to align with premium glass-card aesthetic.
- [x] Correct route link prefixes from `/employees` to `/hrm/employees` across page files.

