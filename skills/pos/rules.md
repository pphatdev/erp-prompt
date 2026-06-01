# Point of Sale Workflow Rules

This document specifies the concrete implementation standards, security protocols, database constraints, and business logic validation rules for the Point of Sale (POS) module.

---

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action` pattern. Access is restricted using standard Laravel Eloquent Policies.

### Permission Keys
- **Module**: `pos`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin / Manager Scope
These permissions unlock full terminal configurations, shift overrides, variance sign-offs, and voiding posted sales.

| Feature | Read | Write | Delete | Special / Override |
|---|---|---|---|---|
| `terminal` | `pos.terminal.read` | `pos.terminal.write` | `pos.terminal.delete` | - |
| `shift` | `pos.shift.read` | `pos.shift.write` | `pos.shift.delete` | `pos.shift.approve` (Reconcile variance) |
| `order` | `pos.order.read` | `pos.order.write` | - | `pos.order.void` (Void completed sales) |
| `settings` | `pos.settings.read` | `pos.settings.write`| - | - |

### Feature Matrix — Cashier Scope (Operational)
These permissions are granted to the cashier role. They allow checkout and shift logging, but restrict modifying configuration or voiding invoices.

| Permission | Endpoint(s) | Business Rules / Constraints |
|---|---|---|
| `pos.shift.read` | `GET /pos/shifts/me` | Cashier can only see their own active shift details. |
| `pos.shift.write` | `POST /pos/shifts/open`, `POST /pos/shifts/close` | Allowed to open shifts (record opening float) and close shifts (record closing count). Cannot alter historical shifts. |
| `pos.order.read` | `GET /pos/orders` | Restricted to retrieving sales processed on their active register terminal. |
| `pos.order.write` | `POST /pos/orders`, `POST /pos/orders/suspend` | Perform checkouts, record payments, and suspend/park carts. |

---

## 2. Implementation Standards

### A. Database Schema & Eloquent Relationships

The POS module utilizes five main tables defined in the tenant migration:

```
┌─────────────────────────────────┐
│          pos_terminals          │
├─────────────────────────────────┤
│ id (UUID, PK)                   │
│ name (String)                   │
│ warehouse_id (UUID, FK)         ├────────┐
│ petty_cash_account_id (UUID, FK)├─────┐  │
│ status (String)                 │     │  │
│ tenant_id (String, Index)       │     │  │
└────────────────┬────────────────┘     │  │
                 │                      │  │
                 │ 1                    │  │
                 │                      │  │
                 │ N                    │  │
┌────────────────▼────────────────┐     │  │
│           pos_shifts            │     │  │
├─────────────────────────────────┤     │  │
│ id (UUID, PK)                   │     │  │
│ terminal_id (UUID, FK)          │     │  │
│ cashier_id (UUID, FK)           │     │  │
│ opened_at (Timestamp)           │     │  │
│ closed_at (Timestamp, Nullable) │     │  │
│ opening_float (Decimal 15,2)    │     │  │
│ closing_cash (Decimal 15,2, Null)│     │  │
│ variance (Decimal 15,2, Null)   │     │  │
│ status (String)                 │     │  │
│ tenant_id (String, Index)       │     │  │
└────────────────┬────────────────┘     │  │
                 │                      │  │
                 │ 1                    │  │
                 │                      │  │
                 │ N                    │  │
┌────────────────▼────────────────┐     │  │
│           pos_orders            │     │  │
├─────────────────────────────────┤     │  │
│ id (UUID, PK)                   │     │  │
│ shift_id (UUID, FK)             │     │  │
│ client_uuid (UUID, Unique, Null)│     │  │
│ customer_id (UUID, FK, Nullable)│     │  │
│ subtotal (Decimal 15,2)         │     │  │
│ tax_total (Decimal 15,2)        │     │  │
│ discount_total (Decimal 15,2)   │     │  │
│ grand_total (Decimal 15,2)      │     │  │
│ status (String)                 │     │  │
│ journal_entry_id (UUID, FK, Null)│    │  │
│ tenant_id (String, Index)       │     │  │
└────────────────┬────────────────┘     │  │
                 │                      │  │
                 ├──────────────────────┼──┘
                 │                      │
                 │ 1                    │ N
                 ├──────────────────────┼──────┐
                 │ N                    │      │
┌────────────────▼────────────────┐     │      │
│         pos_order_items         │     │      │
├─────────────────────────────────┤     │      │
│ id (UUID, PK)                   │     │      │
│ order_id (UUID, FK)             │     │      │
│ product_id (UUID, FK)           │     │      │
│ quantity (Decimal 8,2)          │     │      │
│ unit_price (Decimal 15,2)       │     │      │
│ discount (Decimal 15,2)         │     │      │
│ tax_amount (Decimal 15,2)       │     │      │
│ total (Decimal 15,2)            │     │      │
│ tenant_id (String, Index)       │     │      │
└─────────────────────────────────┘     │      │
                                        │      │
┌───────────────────────────────────────▼┐     │
│             pos_payments               │     │
├────────────────────────────────────────┤     │
│ id (UUID, PK)                          │     │
│ order_id (UUID, FK)                    │     │
│ payment_method (String)                │     │
│ amount (Decimal 15,2)                  │     │
│ reference_number (String, Nullable)    │     │
│ tenant_id (String, Index)              │     │
└────────────────────────────────────────┘     │
                                               │
┌──────────────────────────────────────────────▼┐
│             accounts (FMS)                    │
├───────────────────────────────────────────────┤
│ id (UUID, PK)                                 │
└───────────────────────────────────────────────┘
```

#### Eloquent Model Invariants
1. **Primary Keys**: All models utilize UUID strings generated during the `creating` event.
2. **Tenancy isolation**: Models implement the `BelongsToTenant` trait. `PosOrder` and `PosShift` also utilize the `Auditable` trait and `SoftDeletes` for full tracking.
3. **Database Constraints**:
   - `pos_orders.client_uuid` carries a **unique database constraint** to prevent duplicate checkout creations when syncing offline orders.
   - `pos_terminals.warehouse_id` references `warehouses.id` on delete restrict.
   - `pos_terminals.petty_cash_account_id` references `accounts.id` on delete restrict.

---

### B. Backend (Laravel) Architecture

- **Namespace**: `App\Tenants\Modules\POS`
- **Routing**: Declared inside `routes/tenant.php` prefix `api/v1/pos/`.
- **Services (Thick & Atomic)**:
  - `PosShiftService.php`: Handles cashier shifts: `openShift()`, `addCashFlow()`, `closeShift()`, `reconcileVariance()`.
  - `PosOrderService.php`: Core checkout engine: `checkout()`, `voidOrder()`.
  - `OfflineSyncService.php`: Syncs background offline orders: `syncOfflineOrders()`.
  - **Transaction Enforcements**: Order checkouts, inventory stock-outs, and journal postings must run within a `DB::transaction()` block. If any asset deduction or general ledger entry throws an exception, the entire sale rolls back.

---

### C. Frontend (Nuxt 3) Architecture

- **Path Mapping**: Pages live under `frontend/pages/pos/`:
  - `frontend/pages/pos/register.vue` (Touch grid cart interface)
  - `frontend/pages/pos/shifts.vue` (Cashier logs and manager approvals)
  - `frontend/pages/pos/receipts.vue` (Completed sales histories)
- **Local IndexedDB Caching**:
  - Utilizes a flat composable `frontend/composables/usePosCache.ts` using **Dexie.js** or raw IndexedDB to store the local catalog data.
  - Registers background service workers to capture barcode scans and checkout operations when offline.
- **Offline Sync Daemon**:
  - When connection is recovered, `frontend/composables/usePosSync.ts` queries the local `offline_orders` table.
  - Iterates and executes `POST /api/v1/pos/sync-offline-orders` sending queued carts in batches of 10.
  - Replaces temporary offline order cards with final confirmed API payloads once synced.

---

## 3. Core Business Rules & Validations

### A. Cashier Shift & Drawer Count Lifecycle
1. **Drawer Lock**: Checkout endpoints must reject order submissions if the terminal has no active shift (`PosShift::status = 'open'`), returning `422: Terminal shift is closed`.
2. **Discrepancy Actions**:
   - When a cashier closes their shift:
     - They input their final counted drawer balance.
     - The service calculates `variance = closing_cash - expected_cash`.
     - If `variance != 0`, the shift transitions to `variance_pending`.
     - FMS dispatches a pending alert to managers holding `pos.shift.approve`.
     - Supervisor signs off, shifting the status to `reconciled` and calling FMS to post a journal entry adjusting the cash variance balance (`DR Cash Over/Short Expense / CR Register Petty Cash`).

### B. Double-Entry Posting Rules
On checkout, `PosOrderService` calls the FMS double-entry engine `AccountingService::postEntry()`. The journal entry must record:

$$\text{Debit (Total Paid)} = \sum \text{Payment Method Accounts}$$
$$\text{Credit (Subtotal)} = \text{Sales Revenue Account}$$
$$\text{Credit (Tax)} = \text{VAT/Sales Tax Payable Account}$$

Furthermore, to synchronize real-time stock-out valuations:

$$\text{Debit (WAC Resolved Cost)} = \text{Cost of Goods Sold (COGS) Account}$$
$$\text{Credit (WAC Resolved Cost)} = \text{Inventory Asset Account}$$

Failing to balance any entry (e.g. within $0.001$ precision) must throw a `DomainException`, rolling back all database changes.
