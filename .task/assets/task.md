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
- [x] HRM Custodian Profile Link — Setup Employee model relation (`assets()`) and `EmployeeResource` serialization to discover custody assets directly from employee workspace profile.
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
- [x] Flat page components: `frontend/pages/assets/index.vue` (577 lines - registry with KPI strip, search + status segmented + condition filter, asset cards, create/edit modal, QR modal, archive); `depreciation.vue` (250 lines - log list + per-asset Run/Preview); `revaluation.vue` (259 lines - log list + record-revaluation modal); `disposal.vue` (278 lines - log list + sale/scrap/writeoff wizard); plus `audits.vue` (420 lines - audit campaign workflow, beyond original scope).
- [x] `frontend/composables/useAssets.ts` (253 lines): typed wrappers for every endpoint (assets, depreciation, revaluation, disposal, audit campaigns, verifications, scan profile). Domain types mirror the camelCase backend resources.
- [x] `frontend/stores/assets.ts` Pinia store.
- [x] Interactive KPI strip + condition dropdowns + status segmented + custom modals via design-token classes (badge-soft-*, glass-card, font-mono).
- [x] Kebab action menus, locale-aware date formatting, toast confirmations.

### Phase 7: Verification & QA
- [x] P0 Tenancy Isolation - `tests/Feature/Tenant/Assets/AssetsTenancyIsolationTest.php` (3 cases): Tenant A writes Asset / DepreciationLog / AssetRevaluationLog + AssetDisposal; Tenant B's connection sees 0 of each via `BelongsToTenant` global scope and `find()` returns null.
- [x] P0 Math Accuracy - `tests/Feature/Tenant/Assets/DepreciationMathTest.php` (7 cases): straight-line first period (12000/0/36m -> 333.33), straight-line salvage cap (final period truncates to remaining), already-fully-depreciated returns 0, DDB factor=2 first period rate (NBV * 2/months), DDB salvage cap, SYD first period fraction L/denominator, SYD salvage cap.
- [x] P1 FMS rollback - `tests/Feature/Tenant/Assets/AssetsRollbackAndAuditTest.php::test_fms_failure_rolls_back_asset_and_depreciation_log`: stubs `FmsIntegrationService::postDepreciationJournal` to throw `RuntimeException('Fiscal period locked.')` via container bind; asserts asset.accumulated_depreciation rolls back to 0 + no DepreciationLog row survives.
- [x] P1 Audit log - same test file `::test_auditable_trait_logs_*` uses `Log::spy()` and `Log::shouldHaveReceived('info')->withArgs(...)` to assert `Audit Log: create`/`update` was logged for Asset create, Asset update (post-depreciation accumulated bump), and DepreciationLog create.
- [ ] Postman REST mock examples (deferred).
