# Task Context: Fixed Asset Management

Detailed checklists for implementing the multi-tenant Fixed Assets Management system.

## Checklist

### Phase 1: Database Schema & Tenant Models
- [x] Create PostgreSQL migration `database/migrations/tenant/2024_01_01_000073_extend_assets_for_lifecycle.php` extending the existing `assets`/`depreciation_logs` schema and creating two new tables:
  - `assets` (extended: `asset_code` rename, `purchase_price`/`useful_life_months` rename, `serial_number`, `description`, `vendor_name`, `accumulated_depreciation`, `condition`, `qr_code_url`, `notes`, `custodian_employee_id`, composite `(asset_code, tenant_id)` unique, partial `(serial_number, tenant_id)` unique)
  - `depreciation_logs` (existing — added `method` column)
  - `asset_revaluation_logs` (NEW: previous_value, appraisal_value, adjustment_amount, adjustment_type, appraiser, journal_entry_id)
  - `asset_disposals` (NEW: disposal_type, sale_price, final_nbv, gain_loss, gain_loss_type, journal_entry_id)
- [x] Setup model files flat under `App\Models\Tenant\`:
  - `Asset.php` (updated)
  - `DepreciationLog.php` (updated — replaces planned `AssetDepreciationLog`)
  - `AssetRevaluationLog.php` (new)
  - `AssetDisposal.php` (new)
  - (`AssetDepreciationBook` rolled into the `Asset` row via the `accumulated_depreciation` column + computed `net_book_value` accessor — avoids a redundant 1:1 sibling table.)
- [x] Incorporate `BelongsToTenant`, `SoftDeletes`, and `Auditable` traits.
- [x] Define standard attribute casting rules (floats, dates, enums, auto-UUID boots) + NBV accessor on `Asset`.

### Phase 2: Core Asset Tracking & Identity
- [x] Code `AssetService::create` integrating sequential code generator (`nextAssetCode()` — zero-padded 5-digit suffix).
- [x] Create numbering settings parameter `numbering.asset_code_prefix` inside `SettingService::defaults()` (default `AST-`).
- [x] Add unique index constraint on `(tenant_id, asset_code)` (migration 73).
- [x] Write the QR URL generation helper utilizing standard subdomain routing (`AssetService::buildQrUrl`).
- [x] Implement location assignments and employee custodian mapping (`custodian_employee_id` FK + Employee relation).

### Phase 3: Mathematical Depreciation Engine
- [x] Develop `DepreciationService::calculateNextMonthlyDepreciation` supporting:
  - Straight-Line algorithm.
  - Declining Balance algorithm (configurable `db_factor`, default 2 = double).
  - Sum-of-the-Years'-Digits (SYD) algorithm.
- [x] Code the **Math Invariant Guards** (amount auto-capped at `nbv - salvage_value` so NBV never drops below salvage; accumulated cap enforced by the same equation).
- [ ] Set up the automated `MonthlyDepreciationSchedulerJob` (daily cron) — **DEFERRED**.

### Phase 4: Full-Stack Integrations (FMS, HRM, Fleet)
- [x] Integrate `DepreciationService` with `FmsIntegrationService::postDepreciationJournal` synchronously under `DB::transaction()` — rolls back asset state on FMS exception (e.g. locked period).
- [x] `FmsIntegrationService::postRevaluationJournal` handles surplus (Dr Asset / Cr Reserve) and loss (Dr Loss / Cr Asset).
- [x] `FmsIntegrationService::postDisposalJournal` posts sale (Cash + AccumDepr + Gain/Loss vs Asset Cost) and scrap (AccumDepr + Loss vs Asset Cost).
- [x] Seed missing GL accounts (1500 Accumulated Depreciation, 1700 Fixed Assets, 3200 Revaluation Reserve, 4300 Disposal P&L, 5500 Revaluation Loss) into `seedChartOfAccounts`.
- [ ] Link employee exit callbacks in `EmployeeService` or exit observers to flag unreturned custodian assets — **DEFERRED**.
- [ ] Polymorphic bindings on `Asset` for `Fleet\Vehicle` capitalization — **DEFERRED**.

### Phase 5: Controllers & Access Control
- [x] Scaffold `AssetController` (full CRUD), `DepreciationController` (index + calculate + preview), `RevaluationController` (index + store), `DisposalController` (index + store).
- [x] Configure `AssetResource`, `DepreciationLogResource`, `AssetRevaluationResource`, `AssetDisposalResource` to output camelCase (`assetCode`, `purchasePrice`, `salvageValue`, `usefulLifeMonths`, `netBookValue`, `accumulatedDepreciation`, `custodianEmployeeId`, `qrCodeUrl`).
- [x] Enforce permission-checking policies (`AssetPolicy` registered in `TenantServiceProvider`).
- [x] Support employee custodian self-service checks (`assets.tracking.read.self` + `assets.tracking.write.self`).
- [x] Create `AssetsPermissionSeeder` and plug it into `TenantDatabaseSeeder::run` (`$this->call(AssetsPermissionSeeder::class)`).
- [x] Register routes in `routes/tenant.php` under `auth:api` (apiResource + nested depreciation / revaluation / disposal actions; static `/assets/depreciation` etc. declared before apiResource to avoid being captured as `{asset}` UUIDs).

### Phase 6: Nuxt Premium UI Shell
- [ ] Create flat page components:
  - `frontend/pages/assets/index.vue` (asset grid, table list, QR modals, condition dropdowns)
  - `frontend/pages/assets/depreciation.vue` (depreciation books, batch runner, calendar)
  - `frontend/pages/assets/revaluation.vue` (appraisal inputs, revaluation tables)
  - `frontend/pages/assets/disposal.vue` (scrapping, write-off forms, sale wizard)
- [ ] Create the flat client composable helper `frontend/composables/useAssets.ts` routing through `useApi()`.
- [ ] Create the state store `frontend/stores/assets.ts`.
- [ ] Build interactive dashboards featuring total book values, monthly schedules, and scan status counters using HSL theme variables.
- [ ] Implement standard kebab menus for row actions, date formatting, and toast notifications.

### Phase 7: Verification & QA
- [ ] Write P0 Tenancy Isolation tests asserting Tenant A users cannot read/modify Tenant B assets.
- [ ] Write P0 Math Accuracy tests verifying Straight-line caps and declining formulas.
- [ ] Write P1 FMS ledger integration tests verifying transaction rollbacks on locked period exceptions.
- [ ] Write P1 Audit log assertions validating trait logs are successfully written.
- [ ] Add REST mock examples inside `docs/postman/erp_collection.json`.
