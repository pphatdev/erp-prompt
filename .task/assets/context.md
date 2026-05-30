# Feature Context: Fixed Asset Management

The **Fixed Asset Management** module monitors and governs the end-to-end lifecycle of high-value company assets (capitalization, tracking, depreciation, appraisals, and disposals) under strict multi-tenant database isolation.

---

## 1. Architectural Blueprint

### A. Database Layer (Tenant Migrations)
Create migrations under `database/migrations/tenant/` using UUID keys:
1. `assets`: Core ledger including `asset_code`, `serial_number`, `purchase_price`, `salvage_value`, `useful_life_months`, `depreciation_method`, `custodian_employee_id`, `location_id`, and `status` (`draft`, `active`, `retired`).
2. `asset_depreciation_books`: Tracks accumulated depreciation, current Net Book Value (NBV), and status.
3. `asset_depreciation_logs`: Granular log of each monthly depreciation post (amount, journal entry ID, posting date).
4. `asset_revaluation_logs`: History of appraisals, adjustments, and revaluation reserve journal keys.
5. `asset_disposals`: Retrospective closing values (sale price, final NBV, scrap indicator, gain/loss, journal ID).

### B. Backend Services & Access Control
- **Namespace**: `App\Tenants\Modules\Assets\`
- **Models**: `Asset`, `AssetDepreciationBook`, `AssetDepreciationLog`, `AssetRevaluationLog`, `AssetDisposal` flat under `App\Models\Tenant\`.
- **Services**:
  - `AssetService`: Handles acquisition, tagging, location mapping.
  - `DepreciationService`: Schedules runs, calculates formulas, triggers balanced journals.
  - `RevaluationService`: Manages professional appraisers, updates NBV, posts revaluation reserves.
  - `DisposalService`: Resolves scrap, sale, capital gain/loss, soft-deletes assets.
- **Policies**: `AssetPolicy` enforcing permissions:
  - `assets.tracking.{read,write,delete,export}`
  - `assets.depreciation.{read,write,export}`
  - `assets.revaluation.{read,write,export}`
  - `assets.disposal.{read,write,export}`
  - `assets.tracking.read.self` (Employee Custodian view)

### C. Frontend Layer (Nuxt 3)
- **Pages**: organized flat by URL:
  - `frontend/pages/assets/index.vue` — Main asset directory, QR preview, and condition logs.
  - `frontend/pages/assets/depreciation.vue` — Depreciation books, calculators, and monthly batch runner.
  - `frontend/pages/assets/revaluation.vue` — Appraisal inputs and revaluation logs.
  - `frontend/pages/assets/disposal.vue` — Retirement options, sales, and scrap processing.
- **Composables & Stores**:
  - `frontend/composables/useAssets.ts` flat wrapper mapping standard endpoints via `useApi()`.
  - `frontend/stores/assets.ts` state manager.

---

## 2. Phased Implementation Roadmap

```
Phase 1: DB Schema  ===>  Phase 2: Tracking  ===>  Phase 3: Depr. Engine
        |                        |                         |
        v                        v                         v
Phase 7: QA Tests   <===  Phase 6: UI Shell  <===  Phase 4 & 5: API & FMS
```

### Phase 1: DB Schema & Model Layout
- [ ] Create `database/migrations/tenant/{date}_create_fixed_assets_tables.php` defining the 5 required structures with foreign keys to central domains.
- [ ] Set up flat models under `App\Models\Tenant\` implementing `BelongsToTenant`, `SoftDeletes`, and `Auditable` traits.
- [ ] Define precise casts (e.g. `purchase_price` as float/decimal, `depreciation_method` as enum).

### Phase 2: Asset Tracking & Configurable Tagging
- [ ] Implement asset creation in `AssetService` integrating the unique asset code sequence generator (based on prefix settings like `AST-`).
- [ ] Develop QR code generation helpers utilizing tenant-specific subdomains (e.g., `https://{handle}.domain.com/assets/verify/{uuid}`).
- [ ] Wire location mapping and employee custodian hooks.

### Phase 3: Mathematical Depreciation Engine
- [ ] Build mathematical routines inside `DepreciationService` supporting:
  - Straight-Line: Monthly allocation capped at salvage value limit.
  - Declining Balance: Accelerated scaling utilizing configurable factors.
  - Sum-of-the-Years'-Digits (SYD): Fraction-based asset cost depreciation.
- [ ] Validate core math invariants (NBV must remain $\ge$ Salvage Value, and Accumulated Depreciation must not exceed Cost - Salvage).

### Phase 4: FMS, HRM, & Fleet Integrations
- [ ] Connect `DepreciationService` with `FmsIntegrationService` to post automated monthly journal entries inside a secure `DB::transaction()`.
- [ ] Wire asset disposal journal balance adjustments inside `DisposalService` (handling Cash, AR, Accumulated Depreciation, and Disposal Gain/Loss GL posts).
- [ ] Link `AssetPolicy` and employee exit callbacks to ensure all custodian-linked assets are flagged upon exit.
- [ ] Set up polymorphic association support to capitalize `Fleet\Vehicle` records as assets.

### Phase 5: REST API Controller & Policies
- [ ] Implement thin controllers (`AssetController`, `DepreciationController`, `RevaluationController`, `DisposalController`) utilizing Policies.
- [ ] Configure `AssetResource` to format responses in camelCase and wrap with the standard pagination envelope.
- [ ] Append API routes in `routes/tenant.php` within the `auth:api` group.
- [ ] Create `AssetPermissionSeeder` and plug it into `TenantDatabaseSeeder` for new tenants.

### Phase 6: Nuxt Premium UI & Auditing
- [ ] Scaffold flat pages `/assets/index.vue`, `/assets/depreciation.vue`, `/assets/revaluation.vue`, and `/assets/disposal.vue`.
- [ ] Build the dynamic composable `useAssets.ts` utilizing `useApi()`.
- [ ] Code a responsive assets dashboard using custom Glassmorphism components, detailed lists with kebab row menus, QR scanner modals, and date/currency helpers.

### Phase 7: Verification & QA
- [ ] Write P0 Tenancy Isolation tests (acting as Tenant A and requesting Tenant B resources).
- [ ] Write P0 Mathematical/Financial Accuracy tests for straight-line caps and declining balance formulas.
- [ ] Write P1 FMS ledger integration tests verifying transaction rollbacks on locked period exceptions.
- [ ] Write P1 Audit log assertions validating trait logs are generated.
- [ ] Update `docs/postman/erp_collection.json` with REST examples.
