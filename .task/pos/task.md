# Task Checklist: Point of Sale (POS)

> See [`skills/pos/skill.md`](../../skills/pos/skill.md) for the canonical POS scope. This module is the execution engine for cashier shift floats, fast barcode checkouts, offline caching, and real-time inventory and FMS ledger postings.

Legend: ✅ shipped · ◐ partial · ⬜ planned

---

## A. Core Database & Model Scaffolding (Planned)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.A*

- [ ] Create tenant migration `2026_06_01_000001_create_pos_tables.php` setting up 5 core tables.
- [ ] Set up primary key UUID boots, `SoftDeletes`, and `Auditable` traits on `PosTerminal`, `PosShift`, `PosOrder`, `PosOrderItem`, and `PosPayment`.
- [ ] Ensure database constraints (unique `client_uuid` on `pos_orders` to prevent double checkout syncs).
- [ ] Import and verify multi-tenant scoping via `BelongsToTenant`.

---

## B. Backend Services & Logic (Planned)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.B, § 3*

- [ ] **Shift Float Controls**:
  - [ ] Implement `PosShiftService::openShift()` recording opening cash and locking terminal to cashier.
  - [ ] Implement drawer variance calculations on shift close (`closeShift()`).
  - [ ] Block active checkouts if register active shift is closed.
  - [ ] Build supervisor override approvals (`reconcileVariance()`) adjusting Cash Over/Short accounts in FMS.
- [ ] **Point of Sale Checkout Engine**:
  - [ ] Implement `PosOrderService::checkout()` inside atomic `DB::transaction()` blocks.
  - [ ] Deduct inventory quantity instantly calling `InventoryService::recordMovement` (type='retail_sale') from terminal's warehouse.
  - [ ] Calculate WAC cost values and write cost movement logs in the stock ledger.
  - [ ] Wire FMS postings calling `AccountingService::postEntry()` to write balanced journal debits/credits (Cash/Card vs. Revenue/Tax/COGS/Inventory).

---

## C. API Layer & Access Policies (Planned)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 1, § 2.B*

- [ ] Wire POS routing inside `routes/tenant.php` prefix `api/v1/pos/`.
- [ ] Create controllers `PosShiftController`, `PosTerminalController`, `PosOrderController`, and `PosReceiptController`.
- [ ] Implement Resources serialization formatting snake_case fields into camelCase JSON envelopes.
- [ ] Create and register permissions policies (`PosTerminalPolicy`, `PosShiftPolicy`, `PosOrderPolicy`) in `TenantServiceProvider`.
- [ ] Seed standard POS permissions in the central database tables.

---

## D. Frontend Page Scaffolding & Routing (Planned)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.C*

- [ ] Scaffold folder structure inside Nuxt: `frontend/pages/pos/`.
- [ ] Register navigation routes and icons (`ti-cash-register`) inside the sidebar configuration gating on `pos.terminal.read` permission.
- [ ] Define the `usePos` composable (`frontend/composables/usePos.ts`) and register Pinia store (`frontend/stores/pos.ts`).

---

## E. Frontend Workspaces & Views (Planned)
*Reference: [`skills/pos/overview.md`](../../skills/pos/overview.md) § 1-5*

### 1. Shift Controls & Dashboards (`/pos/shifts`)
- [ ] **Float Registration Overlay**: Cashier opening dialog collecting cash floats before register workspace unlock.
- [ ] **Discrepancy Approvals Board**: Supervisor dashboard to review pending variances, view counting slips, and override variance blocks.

### 2. Touch Screen Register Workspace (`/pos/register`)
- [ ] **Touch Grid Quick Keys**: Localized catalog navigation showing HSL-colored product categories and quick pick grids.
- [ ] **Scanner Barcode Parsing**: Barcode scanner event listener automatically incrementing matching cart lines without losing cursor focuses.
- [ ] **Parked Carts Manager**: Panel enabling cashiers to suspend active checkouts and retrieve parking cards.
- [ ] **Payments & Change Calculators**: Split-tender payments overlays (split card + cash) showing changes due.
- [ ] **Thermal Print Styling**: Receipt visual template wrapping print requests inside thermal compact css profiles (`thermal-receipt` class) hiding standard layout chromes.

### 3. Client-Side Resiliency & Sync Daemons (Planned)
- [ ] **IndexedDB Local Cache**: Implement Dexie.js or raw IndexedDB caching product items, prices, and barcodes.
- [ ] **Offline Orders Queue**: Local storage queue saving serialized orders and payments when offline.
- [ ] **Background Sync Daemon**: Connection heartbeat listener executing background sync batches, calling `POST /api/v1/pos/sync-offline-orders` with idempotent dedupe rules.

---

## F. Integration & QA Testing (Planned)
*Reference: [`skills/pos/testing.md`](../../skills/pos/testing.md) § 1-4*

- [ ] **Backend Pest Test Suite**:
  - [ ] Write `TenancyIsolationTest` asserting cross-tenant register shift reads return `404`.
  - [ ] Write `ShiftVarianceTest` verifying mathematical checks and cashier lockouts.
  - [ ] Write `OfflineSyncDedupeTest` asserting identical `client_uuid` requests return idempotent duplicates safely.
- [ ] **Postman Collections Sync**:
  - [ ] Add shift open/close and checkout scenarios inside `docs/postman/erp_collection.json`.
