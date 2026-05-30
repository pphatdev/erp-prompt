---
name: fixed-asset-management
description: Manage physical assets, generate unique QR tracking tags, calculate depreciation schedules, handle professional appraisals, and execute asset disposal.
---
# Fixed Asset Management

Use this skill when developing, expanding, or integrating components related to physical asset management, QR tracking systems, depreciation calculators, professional revaluations, and asset retirements.

---

## 1. Primary Workflows

### A. Asset Acquisition & Capitalization
1. **Capitalization Guard**: Filter approved procurement bills. Items exceeding the tenant's threshold (e.g. $1,000) are flagged.
2. **Ledger Registration**: Save the asset with purchase price, acquisition date, useful lifespan (in months), vendor, serial number, and salvage value.
3. **Tagging & Identity**: Automatically generate a sequential, zero-padded asset code (e.g., `AST-00042`) based on the tenant's active prefix settings.
4. **QR Generation**: Create a dedicated web/mobile-scannable QR code linking to the tenant-isolated asset route.

### B. Depreciation Schedule & Monthly Runs
1. **Calculation**: Compute monthly depreciation using Straight-line, Declining Balance (Single/Double), or Sum-of-the-Years'-Digits (SYD).
2. **Invariants Guard**: Assert that Net Book Value (NBV) never falls below the asset's configured `salvage_value`.
3. **Ledger Integration**: Call `FmsIntegrationService` synchronously inside a DB transaction to create balanced journal entries:
   - **Debit**: Depreciation Expense Account
   - **Credit**: Accumulated Depreciation Account
4. **Audit Trail**: Record the updated NBV and accumulated depreciation on the asset along with an audit log of the actor.

### C. Asset Appraisals & Revaluations
1. **Appraisal Record**: Log third-party or internal professional valuations.
2. **Ledger Adjustment**: Adjust asset book value to match appraisal.
3. **Surplus/Loss Postings**: Post adjustments to FMS:
   - If appraisal is higher: **Debit** Asset Value, **Credit** Revaluation Reserve (equity account).
   - If appraisal is lower: **Debit** Revaluation Loss (expense account), **Credit** Asset Value.

### D. Physical QR Scans & Audits
1. **Verification Campaigns**: Initiate a bi-annual audit event requiring custodians or managers to scan assets.
2. **Field QR Scan**: Resolve the tenant subdomain, verify the signature, and load the specific asset profile.
3. **Reconciliation**: Update condition state (`Excellent`, `Good`, `Fair`, `Poor`, `Damaged`) and current physical location. Flag location mismatches in real-time.

### E. Asset Disposal & Retirement
1. **Lifecycle Finalization**: Ensure depreciation calculations are posted up to the active disposal date.
2. **Net Book Value Computation**: Calculate remaining NBV ($NBV = \text{PurchasePrice} - \text{AccumulatedDepreciation}$).
3. **Gain/Loss Ledger Entry**: Execute disposal journal entries matching the transaction type:
   - **Sale**: Debit Cash/AR (sold amount), Debit Accumulated Depreciation, Credit Asset Cost, Debit/Credit Gain or Loss on Disposal.
   - **Scrap/Write-off**: Debit Accumulated Depreciation, Debit Loss on Disposal (equivalent to NBV), Credit Asset Cost.
4. **Retirement**: Update asset status to `retired` and soft-delete the model.

---

## 2. Structural Guidelines

- **Database Isolation (P0)**: Under no circumstances should cross-tenant asset reads or writes occur. Every asset table is strictly scoped with the `BelongsToTenant` trait.
- **CamelCase Contract (P0)**: All REST API payloads and responses must follow camelCase keys. Ensure controllers return the `JsonResource` instance directly.
- **Frontend Directory Layout**: All pages reside in `frontend/pages/assets/` (no `src/modules/` folders). Composable helpers go into `frontend/composables/useAssets.ts` and state stores into `frontend/stores/assets.ts`.
- **Kebab Actions (P2)**: Any asset datatable action menu with 2 or more actions must use a 30x30px kebab menu wrapper with absolute dropdown positioning.

---

## 3. Cross-Module Integrations

- **HRM (Employees)**: Track active custodians by linking the asset to `employee_id`. When an employee exits the tenant organization, trigger a middleware checker to flag all unreturned assets.
- **Fleet (Vehicles)**: Capitalize heavy machinery or company vehicles by registering them in both Fleet and Fixed Assets systems. A polymorphic relation allows the vehicle model to also act as an auditable fixed asset.
- **FMS (General Ledger)**: Execute direct journal entries via the `FmsIntegrationService`. Verify that the tenant's ledger period is open before executing any posting transaction.

---

## 4. Troubleshooting & Verification Recipes

### A. Mismatched Depreciation Ledger
- **Symptom**: The calculated monthly depreciation does not match expectations, or the net book value falls below the salvage value.
- **Fix**: Check `AssetDepreciationBook` fields. Ensure `salvage_value` and `useful_life_months` are correctly configured. Assert that `accumulated_depreciation` has not exceeded the cap $(\text{purchase\_price} - \text{salvage\_value})$.

### B. FMS Period Locked Exception
- **Symptom**: Automated monthly depreciation run crashes with a `LedgerPeriodLockedException` or `422 Unprocessable Entity` from FMS.
- **Fix**: Direct the tenant manager to **Settings → Accounting Periods** to open the target fiscal month, or configure the job to queue and retry once the period is opened.

### C. Duplicate Asset Registration
- **Symptom**: Multiple assets registered with the same serial or chassis number.
- **Fix**: Enforce unique indexes on `(tenant_id, serial_number)` within the migration schema. Validate the serial number uniqueness inside `AssetService::create` before executing database insert.

### D. QR Scan Resolves to Central Domain
- **Symptom**: Scanning a physical QR code on an asset redirects the user to the base central domain, losing the tenant connection.
- **Fix**: Ensure QR generation helpers construct tenant-specific URLs utilizing standard subdomain routing: `https://{tenant_handle}.domain.com/assets/verify/{id}`.
