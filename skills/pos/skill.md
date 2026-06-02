---
name: point-of-sale
description: Retail Point of Sale including cashier shifts, barcode checkout, split payments, offline synchronization, and real-time inventory and FMS postings.
---
# Point of Sale (POS)

Use this skill when developing, extending, or maintaining cashier registers, touch layouts, shift drawer managers, barcode checkouts, offline caching, split-tender operations, receipt thermal printing, or immediate financial-inventory ledger integration. This module deals with cash handling and real-time stock deductions — multi-tenant isolation and double-entry accuracy are P0 security vectors.

## Module Surface

```
Point of Sale (sidebar group)
├── Register Screen                — Active cashier sales layout, touchscreen grid, cart
│   └── Parked Carts Panel         — View and resume suspended customer checkouts
├── Shifts & Cashiers              — Opening float, cashier logs, closing drawer counting
│   └── Shift Approvals (Manager)  — Supervisorial variance sign-offs and cash counts
├── Register Terminals             — Terminals configuration, link warehouses & cash accounts
├── Retail Receipts                — Paginated list of completed retail sales receipts
└── POS Settings                   — Custom Quick Pick keys, barcode parsers, tax overrides
```

| Layer | Path |
|---|---|
| **Controllers** | `app/Tenants/Modules/POS/Controllers/{PosShiftController, PosTerminalController, PosOrderController, PosReceiptController}.php` |
| **Services** | `app/Tenants/Modules/POS/Services/{PosShiftService, PosOrderService, OfflineSyncService}.php` |
| **Resources** | `app/Tenants/Modules/POS/Resources/{PosShiftResource, PosTerminalResource, PosOrderResource, PosReceiptResource}.php` |
| **Models** | `app/Models/Tenant/{PosTerminal, PosShift, PosOrder, PosOrderItem, PosPayment}.php` |
| **Policies** | `app/Policies/{PosTerminal, PosShift, PosOrder}Policy.php` |
| **Migrations** | `database/migrations/tenant/{date}_create_pos_tables.php` |
| **Seeder** | `TenantDatabaseSeeder.php` — seeds default POS terminals, registers, workflow statuses, tax allocations, and dummy product "Quick Pick" associations. |
| **Pages** | `frontend/pages/pos/{register, shifts, terminals, receipts}.vue` |

---

## Permission Slug Catalog

```
pos.terminal.{read,write,delete}
pos.shift.{read,write,delete}
pos.shift.approve                         ← Supervisor/Manager role to reconcile drawer variances
pos.order.{read,write}                    ← Standard cashier checkout access
pos.order.void                            ← Supervisor override to void a posted retail invoice
pos.settings.{read,write}                 ← Admin config for register quick-keys and printer settings
```

---

## Critical Rules

### 1. Cashier Shift Isolation & Drawer Controls (P0)
- **Float Initialization**: A terminal is locked and blocks checkout operations until a cashier initializes a new `PosShift` session by recording the `opening_float` amount.
- **Drawer Balance Isolation**: All cash sales, card transactions, cash drops (skims), and payouts are logged against the active `PosShift` record. Cashiers can only view their own open shift logs.
- **Reconciliation & Variance Enforcement**: Closing a shift requires the cashier to submit a *Closing Cash Count* (`closing_cash`). The `PosShiftService::closeShift()` automatically calculates the drawer variance:
  $$\text{Variance} = \text{Closing Cash Count} - (\text{Opening Float} + \text{Cash Sales} + \text{Cash Drops} - \text{Cash Payouts})$$
  - If the variance is non-zero (over/short), the shift status moves to `variance_pending`.
  - Shifts flagged as `variance_pending` restrict the cashier from opening new shifts until a supervisor with `pos.shift.approve` signs off and reconciles the variance, posting the discrepancy to the FMS Cash Over/Short expense account.

### 2. Barcode Parsing & Cart Calculations (P1)
- **Scanning Invariant**: The touchscreen register interface binds a fast input listener to barcode scanner feeds. Scan inputs must parse UPC/EAN formats, check the local cache, and increment the quantity of the matching `ProductVariant` record instantly.
- **Tax Calculation Integrity**: Tax is calculated in real-time on checkout. Products are queried for their associated tax rate. The backend validates that total tax matches:
  $$\text{Tax Total} = \sum (\text{Line Total} \times \text{Tax Rate})$$
  Failing to match calculates tax at the base tenant retail rate (e.g., 10% VAT).
- **Price Override Validation**: Cashiers can apply percentage/fixed discounts to items *only* if the discount percentage is lower than the register's maximum cashier override limit (default: 15%). Exceeding this triggers a supervisor authorization modal prompting credentials holding `pos.order.void`.

### 3. Client-Side Offline Resiliency & IndexedDB Sync (P1)
- **Local Storage Cache**: The frontend register layout stores the active product list, prices, barcode mappings, and taxes locally inside IndexedDB via the `usePosCache` composable. This cache refreshes in the background every 30 minutes when online.
- **Offline Checkout Queue**: If the network state changes to `offline`, checkout completes locally. The sale is serialized, signed with a temporary client UUID, and queued inside the IndexedDB queue `offline_orders`. The cashier receipt is printed with a watermark: `OFFLINE RECEIPT - PENDING SYNC`.
- **Heartbeat Reconciliation**: Once online, a sync daemon (`OfflineSyncService`) uploads the queued orders in first-in-first-out (FIFO) order using a dedicated endpoint `POST /api/v1/pos/sync-offline-orders`.
  - **Collision Resolution Contract**: If an order contains a client UUID that already exists in the backend, the backend rejects it with `200 OK` (idempotent deduplication). If an offline order contains a stock out that exceeds available stock, the backend records the transaction but flags the inventory movement as `insufficient_stock_warning` for manager manual override, avoiding front-counter sale blockages.

### 4. Real-time Stock Out & WAC Movement Logging (P0)
- **Instant Allocation**: Complete orders must deduct inventory immediately. Call `StockService::recordMovement` with a transaction type (`type` = `'out'`) and a `reference` prefix of `'POS:'` (e.g. `'POS:{order_number}'`) scoped to the terminal's target `warehouse_id`.
- **WAC Synchronization**: The deduction resolves inventory value at the current Weighted Average Cost (WAC). If a retail sale triggers a negative warehouse stock balance, the cost resolves from the last known WAC.

### 5. Double-Entry FMS Posting (P0)
- **Atomic Checkout Posting**: Order processing must occur within a single database transaction.
- **General Ledger Alignment**: The service calls `AccountingService::postEntry()`, outputting balanced GL lines:
  * `DR Cash Account (Register petty cash)` OR `DR Card Receivable` $\rightarrow$ Total Paid.
  * `CR Retail Sales Revenue` $\rightarrow$ Subtotal.
  * `CR Tax Payable (VAT/Sales)` $\rightarrow$ Tax Total.
  * `DR Cost of Goods Sold (COGS)` $\rightarrow$ Total cost (derived from WAC) today; if COGS accounting is disabled, only the retail sales lines are posted.
  Any ledger posting failure rolls back the order creation and alerts the terminal cashier.

---

## Status Flows (Workflow System Integration)

Consistent with multi-tenant rules, POS shifts and orders resolve from the central `workflow_statuses` lookup tables.

### POS Shift Workflow States
| State Key | Initial/Terminal | Meaning | Action Trigger |
|---|:---:|---|---|
| `closed` | Initial | Register is locked; cashier cannot scan items. | Previous cashier closed session. |
| `open` | Active | Active cashier session; sales are enabled. | Cashier logs opening float. |
| `variance_pending`| Active | Shift completed with cash mismatch; locked. | Closing count submitted with over/short. |
| `reconciled` | Terminal | Mismatch signed off by manager; archived. | Supervisor enters override credentials. |

### POS Order Workflow States
| State Key | Initial/Terminal | Meaning | Action Trigger |
|---|:---:|---|---|
| `draft` | Initial | Suspended/parked cart layout. | Cashier clicks "Park Cart". |
| `paid` | Terminal | Completed checkout; receipt generated. | Payments fully settle order total. |
| `voided` | Terminal | Cancelled transaction; reverses inventory. | Supervisor voids completed sale. |

---

## Frontend Integration Standards

- **Touch Grid Quick Keys**: The UI features a high-performance grid layout (`pages/pos/register.vue`) loading item tiles with product images. Quick pick tabs are cached locally for instantaneous viewport renders.
- **Thermal Printer Hook**: Integrates direct raw ESC/POS thermal printing or standard window print bindings mapping onto an 80mm compact styled stylesheet wrapper (`thermal-receipt` class) hiding the standard layout chrome.

---

## Troubleshooting Matrix

| Symptom | Root Cause | Programmatic Resolution |
|---|---|---|
| **Broadcasting auth 403 on open shift** | Cashier does not hold `pos.shift.read`. | Check `PosShiftPolicy::view`. Ensure cashier roles are granted shift-view capabilities scopes. |
| **Offline synced sales create duplicate inventory cuts** | The client UUID syncer is not matching deduplication keys. | Ensure the backend `PosOrderService::syncOffline` does a database check on `client_uuid` and returns early with `linkedExisting: true` without executing duplicate stock movements. |
| **Shift close variance returns incorrect totals** | Card transactions are counted inside the cash float summation. | Check `PosShiftService::calculateVariance`. Cash variances must only count Cash-based payment types, excluding debit/credit card clearings which are settled directly by card terminals. |
| **GL Posting fails with 500** | Register terminal is not linked to a default Petty Cash or Cash-Equivalent asset account. | Add validation in `PosTerminal::creating` checking that `petty_cash_account_id` maps to an asset-type GL account. |

---

## Read Next
- [`overview.md`](./overview.md) — Feature taxonomy, integrations, and concepts.
- [`rules.md`](./rules.md) — Permission matrices, DB schemas, and technical flows.
- [`flow.md`](./flow.md) — Visualizing cashier shifts, offline syncs, and double-entry postings.
- [`testing.md`](./testing.md) — Cash isolation, calculations, and QA test specs.
