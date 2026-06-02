# ERP Master Progress Registry

> Last synced: 2026-06-01 (Accounting > Cash Advance shipped)

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
- [x] Fixed Asset Custody integration — setup model relations and resource JSON structures (eager-loaded endpoints & tab details planned)

## Calendar & Holiday Management
> Full task: [`.task/calendar/task.md`](./.task/calendar/task.md) | Rule: [`skills/calendar/rules.md`](../skills/calendar/rules.md)
- [ ] Schema: Multi-tenant database migrations for holidays and calendar events tables
- [ ] Holiday Registry: configurator managing public dates, regional associations, and 3.0x overtime multipliers
- [ ] Compensatory Days: automated Saturday/Sunday holiday compensations shifting paid days off to Mondays
- [ ] Unified Events compilation: query aggregator consolidating holidays, leaves, shifts, and CRM schedules
- [ ] Privacy Masking: conditional resource serialization hiding sick leave details from unauthorized employees
- [ ] Attendance integration: Daily reconciler holiday status overrides and monthly workday counts adjustments
- [ ] Responsive Dashboard UI: PrimeVue monthly, weekly, daily calendars with togglable checkbox layers



## Inventory
- [x] Products CRUD (with variants, module linking for software type)
- [x] **Product Variants CRUD** — `ProductVariantController` (nested under products + shallow), `ProductVariantService` with cross-table SKU uniqueness, `ProductVariantPolicy` reusing `inventory.product.*` perms. Products page modal gains a dynamic variants editor (add/remove rows, JSON attributes, persisted in a second round-trip after product save).
- [x] **Categories** — hierarchical taxonomy (`categories` table with self-FK parent_id), `Category` model + service (move-under-descendant cycle guard, archive blocked by children/products), `CategoryController` (flat + `?tree=1`), `CategoryPolicy` with `inventory.category.{read,write,delete}`. `/inventory/categories` page with tree view + color chips + parent picker. Category column/filter on the Products page; nullable `category_id` FK added to products.
- [x] Stock Movements ledger & service (recordMovement, transferStock)
- [x] Warehouse management API & PrimeVue UI (`/inventory/warehouses` — CRUD + KPI cards, archive blocked by on-hand stock guard)
- [x] Supplier directory API & PrimeVue UI (`/inventory/suppliers` — CRUD + rating/lead-time/terms, archive blocked by open PO guard)
- [x] Vendor / AP extension on Supplier (`is_vendor` flag, payment method, bank details, default Payable + Expense account FKs to `accounts`). Frontend modal gains a Vendor / AP Details section; list gains "Vendors only" filter and AP badge. Migration 000078.
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

## Accounting & General Ledger
> Full task: [`.task/accounting/task.md`](./.task/accounting/task.md) | Rule: [`skills/accounting/rules.md`](./skills/accounting/rules.md)
>
> **Scope** (per 2026-06-01 taxonomy merge): Accounting is the cross-cutting accountant's lens. Owned features: Chart of Accounts, Journals, Bank, Budget, AR (Receipts / Credit Note / Debit Note), AP (Bill / Pay Bill / Reimbursement / Cash Advance / Advance Settlement / Expense), Exchange Rates. Cross-linked from operational modules: Sales (Customers/Quotation/Invoice), Inventory (Items/PO/WAC/Adjustment/PR), HRM (Employees/Payroll), Assets (Register/Depreciation/Disposal). See [`skills/accounting/overview.md`](./skills/accounting/overview.md).
- [x] Chart of Accounts table migration, hierarchical `Account` model, and CRUD controllers.
- [x] Journal Entries & Ledger table structures, `JournalEntry` and `LedgerEntry` models, and balanced `AccountingService::postEntry` postings.
- [x] Exchange rates database migration, uppercase standardization logic, `ExchangeRateService` converter, and PrimeVue dynamic currency converter UI.
- [x] CoA circular parent-child loops prevention safeguards and deletion protection (`AccountService::update` cycle/parent-type guard; `archive` blocks on children or ledger history).
- [x] Tree view dynamic balance summation (`AccountController@index?tree=1`) and CoA UI page (`/accounting/accounts`) with per-type KPI cards + filter chips.
- [x] Top-level "Accounting" sidebar group (CoA moved out of Finance) + Journals page (`/accounting/journals`) with line builder, live balance indicator, and account picker from CoA tree.
- [x] General Ledger immutability (`JournalEntryPolicy` + `LedgerEntryPolicy` block update/delete; explicit routes replace `apiResource`) and reverse-posting utility (`AccountingService::reverseEntry` with row lock + self-FK link cols).
- [x] Reversal UI on `/accounting/journals` — status chips, per-row Reverse action + modal, inline reversal-link badges, muted/strikethrough styling for reversed rows.
- [ ] Fiscal period lock statuses, post-write validation block middlewares, and closing balance rollover automatons — **P1 open**

## Projects & Time Tracking
> Full task: [`.task/projects/task.md`](./.task/projects/task.md) | Rule: [`skills/projects/rules.md`](../skills/projects/rules.md)
- [x] Hierarchical database migrations (Projects, Tasks, Timesheets) and Eloquent UUID bootstrapping
- [x] Tenant connection scoping via `BelongsToTenant` and event-auditing via `Auditable` P0 guards
- [x] Basic CRUD REST API controllers, camelCase resources, and RBAC policy permission checks
- [ ] Circular Dependency cycles detection DFS engine (P1 backend open)
- [ ] Recursive Date Recalculation Gantt scheduling cascades (P1 backend open)
- [ ] Timesheet hour limits, approved leave blocks, and payroll locks (P1 backend open)
- [ ] Nuxt 3 flat routing, composables (`useProjects.ts`), and Pinia project state stores (Planned UI open)
- [ ] WBS hierarchical TreeTable editor & Gantt interactive timeline schedule (Planned UI open)
- [ ] Kanban Board with PrimeVue drag-and-drop & optimistic UI status updates (Planned UI open)
- [ ] Task detail side drawers with checklist manager, inline comments, and secure attachments (Planned UI open)
- [ ] Self-service daily hours logging portal & Manager timesheet approvals board (Planned UI open)
- [ ] FMS/HRM cost accounting, unbilled AR integration ledger, and Pest test suites (Planned UI open)

## Assets
> Full task: [`.task/assets/task.md`](./.task/assets/task.md) | Rule: [`skills/assets/rules.md`](./skills/assets/rules.md)
- [ ] Schema: Multi-tenant database migrations for 5 assets-related tables and flat Eloquent models utilizing `BelongsToTenant`, `SoftDeletes`, and `Auditable`
- [ ] Tracking: `AssetService` supporting tenant-configurable `numbering.asset_code_prefix` and subdomain-specific QR code URL generations
- [ ] Depreciation: `DepreciationService` calculations (Straight-line, Declining Balance, SYD) and scheduling batch checker jobs
- [ ] Integration: Synchronous balanced general ledger postings to FMS inside atomic database transactions, exit checking for HRM custodians, and polymorphics for Fleet vehicles
- [ ] Access: Thin API controller layers guarded by policies and seeder configurations including self-service `.self` scopes
- [ ] UI: Nuxt 3 pages (`/assets`, `/assets/depreciation`, `/assets/revaluation`, `/assets/disposal`), stores, flat `useAssets.ts` composable, interactive HSL panels, custom modal overlays, and toast confirmations
- [ ] QA: Pest integration testing (Isolation, mathematical thresholds, camelCase structural assertions, transaction rollback traps, audit logs) and Postman collections

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

## Point of Sale (POS)
> Full task: [`.task/pos/task.md`](./.task/pos/task.md) | Rule: [`skills/pos/rules.md`](../skills/pos/rules.md)
- [ ] Schema: Multi-tenant database migrations for 5 POS tables, unique client_uuids, and model constraints
- [ ] Shift Float controls: cashier shifts open/close tracking, cash skims/paid-outs, and mathematical expected cash totals
- [ ] Supervisor variance approvals: over/short balance flagging, FMS Cash Over/Short postings, and cashier session unlocks
- [ ] Transaction checkout: transaction-wrapped sales checkout, WAC stock-outs, and FMS AccountingService GL bookings
- [ ] Offline Resiliency: IndexedDB local product/barcode caching, offline checkouts, and idempotent heartbeat sync daemon
- [ ] Touch Register Interface: touch quick keys grid catalog, barcode scanners focus listener, and thermal receipt css printing

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

