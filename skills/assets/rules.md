# Fixed Asset Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `assets`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin Scope:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `tracking` | `assets.tracking.read` | `assets.tracking.write` | `assets.tracking.delete` | `assets.tracking.export` |
| `depreciation`| `assets.depreciation.read`| `assets.depreciation.write`| - | `assets.depreciation.export`|
| `revaluation`| `assets.revaluation.read`| `assets.revaluation.write`| - | `assets.revaluation.export`|
| `disposal` | `assets.disposal.read` | `assets.disposal.write` | - | `assets.disposal.export` |

### Feature Matrix — `.self` Scope (Employee Self-Service):
Employees/Custodians can view and audit their assigned physical assets:
- `assets.tracking.read.self` — Read assigned physical assets.
- `assets.tracking.write.self` — Update physical audit status (e.g. self-verify condition during stock-take).

---

## 2. Implementation Standards

### Asset Operational Flow
1. **Acquisition & Capitalization**: Register new physical asset, automatically triggering code generation (e.g. `AST-00001`) and initial capitalization criteria checking.
2. **Tagging & Custody**: Assign tracking locations and an employee custodian (`employee_id`), generating the physical QR code.
3. **Depreciation Job**: Run scheduled monthly calculations based on Straight-line, Declining balance, or SYD methods, posting automated journal entries to the `fms` module.
4. **Appraisal & Revaluation**: Periodically adjust asset book value based on certified appraisals, matching revaluation reserve entries in `fms`.
5. **Physical Audit Scans**: Verify assets physically via QR scanners, updating the location and condition status.
6. **Disposal / Scrap**: Retire, write off, or sell assets, calculating Net Book Value (NBV) and posting gain/loss journal entries to FMS.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Assets`
- **Service Layer**: Business operations live strictly in services:
  - `Services/AssetService.php` — Acquisition, updates, and indexing.
  - `Services/DepreciationService.php` — Depreciation schedule generation and monthly posting runs.
  - `Services/RevaluationService.php` — Appraisal logging and value adjustments.
  - `Services/DisposalService.php` — Scrap, sales, write-offs, and final balance closing.
- **Model Invariants (P0)**:
  - All models must reside flat under `App\Models\Tenant\` (e.g. `Asset`, `AssetDepreciationBook`, `AssetRevaluationLog`, `AssetDisposal`) and utilize `BelongsToTenant`, `SoftDeletes`, and `Auditable` traits.
  - **Mathematical Constraints**:
    - **Net Book Value (NBV)** must satisfy: $NBV = \text{PurchasePrice} - \text{AccumulatedDepreciation}$.
    - **Minimum Value**: NBV must never fall below the configured `salvage_value` of the asset.
    - **Depreciation Cap**: Total accumulated depreciation must not exceed $(\text{PurchasePrice} - \text{SalvageValue})$.
  - Multi-table writes (e.g., executing a depreciation run and updating the asset's accumulated depreciation ledger) must be wrapped in `DB::transaction()`.
- **API Security & Policies**:
  - Enforce authorization inside controllers via Policies (`AssetPolicy`, `AssetDepreciationPolicy`, etc.) using standard permission keys.
  - Custodian self-service routes must enforce that the caller matches the assigned `employee_id` on the asset record.
- **Resource JSON Format (P0)**:
  - Keys returned from resources (`AssetResource`, `AssetDepreciationResource`, etc.) **MUST** use camelCase (e.g. `purchasePrice`, `salvageValue`, `usefulLifeMonths`, `netBookValue`, `accumulatedDepreciation`, `custodianEmployeeId`, etc.). Never return raw snake_case database columns directly.
  - Return JsonResource instances directly from controllers to preserve the validation/missing value pipeline.
- **Pagination**:
  - Index endpoints must return the standard pagination envelope: `{ data: [...], pagination: { page, limit, total, totalPages } }`.

### Frontend (Nuxt/PrimeVue)
- **Directory Path Structure**:
  - Pages organize strictly by URL: `frontend/pages/assets/index.vue`, `frontend/pages/assets/depreciation.vue`, `frontend/pages/assets/disposal.vue`, and `frontend/pages/assets/revaluation.vue`. Nested per-module assets inside a `src/modules/` folder are forbidden.
  - Composables, stores, and components must be flat: `frontend/composables/useAssets.ts`, `frontend/stores/assets.ts`, `frontend/components/AssetSelector.vue`.
- **Premium UI**:
  - Display interactive dashboards highlighting: Total Asset Value, Monthly Depreciation Cost, Asset Auditing Progress (Scanned vs Remaining), and Asset Condition Breakdown.
  - Implement full responsive custom card styles using existing design variables (e.g., `--color-primary-rgb`, `.glass-card`).
- **Standard Layout Mechanics (P2)**:
  - **Confirmations**: No native browser `confirm()` or `alert()`. Destructive or critical updates must go through `useToast().confirm()`.
  - **Date Formatting**: Every date or datetime render must use formatting helpers `formatDate` or `formatDateTime` from `~/composables/useDateFormat.ts`.
  - **Table Row Actions**: List tables with $\ge 2$ row actions must use the standard 30x30 kebab trigger (`ti-dots-vertical`) with fixed dropdown positioning and outside-click dismissal.
- **API Fetching**:
  - Always route through `useApi()`. Direct `$fetch` or `useFetch` are prohibited because they bypass tenant-scoping context headers (`X-Tenant-Handle`).

---

## 3. Code Numbering (Tenant-Configurable)
- **Asset Codes**: Support tenant-configurable numbering schemas under **Settings → Numbering** via `numbering.asset_code_prefix` (default `AST-`), falling back gracefully to standard defaults. Zero-padded sequence integers should grow sequentially and are stored in the database.
