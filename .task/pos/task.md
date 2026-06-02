# Task Checklist: Point of Sale (POS)

> See [`skills/pos/skill.md`](../../skills/pos/skill.md) for the canonical POS scope. This module is the execution engine for cashier shift floats, fast barcode checkouts, offline caching, and real-time inventory and FMS ledger postings.

Legend: ✅ shipped · ◐ partial · ⬜ planned

---

## A. Core Database & Model Scaffolding (Shipped)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.A · Scope: Shifts + Checkout only (offline sync / parking / split tender deferred)*

- [x] Tenant migration `2024_01_01_000094_create_pos_tables.php` ships 5 tables:
  `pos_terminals` (code unique per tenant, FK warehouse_id restrict + petty_cash_account_id restrict),
  `pos_shifts` (FSM `open`->`closed`/`variance_pending`->`reconciled`, opening_float / expected_cash / closing_cash / variance),
  `pos_orders` (FSM `paid`/`voided`/`refunded`, denormalized terminal_id+cashier_id, journal_entry_id + void_journal_entry_id, `(tenant_id, client_uuid)` unique for offline-sync idempotency),
  `pos_order_items` (snapshots product_name + product_sku + variant_sku, nullable variant_id),
  `pos_payments` (method=cash|card|wallet|manual, tendered+change_due for cash, reference_number for card auth).
- [x] All models use UUID `creating` boot + `BelongsToTenant`. `PosTerminal` / `PosShift` / `PosOrder` add `Auditable` + `SoftDeletes`; line + payment children skip both.
- [x] `client_uuid` carries a per-tenant unique constraint (`pos_orders_client_uuid_unique`) so an offline-replay can never double-post.
- [x] `customer_id` on `pos_orders` is nullable -> walk-in default with optional Sales\\Customer link.
- [x] FSM constants exposed on each model (`PosShift::STATUS_*`, `PosOrder::STATUS_*`, `PosPayment::METHOD_*`). Helpers: `isOpen()`, `hasVariance()`, `isPaid()`, `isRefundable()`, `isCash()`.
- [x] `numbering.pos_order_prefix` (default `POS-`) appended to `SettingService::defaults()`.

---

## B. Backend Services & Logic (Phase 2 - Shipped)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.B, § 3*

- [x] **PosTerminalService**: CRUD; disable refuses if any open shift; destroy refuses if any order history.
- [x] **PosShiftService::openShift**: refuses if terminal already has an open shift OR cashier already has an open shift on any terminal (lockForUpdate guards races). Validates positive `opening_float`.
- [x] **PosShiftService::closeShift**: computes `expected_cash = opening_float + sum(cash payments on this shift's PAID orders)`, `variance = closing_cash - expected_cash`. Transitions to `closed` when variance is within 0.005, else `variance_pending` for supervisor reconciliation.
- [x] **PosShiftService::activeShiftForCashier**: dashboard helper - "what register am I on right now?".
- [x] **PosOrderService::checkout** (atomic): guards shift is open + tender sums to grand_total + valid payment methods; snapshots line items; calls `StockService::recordMovement` type=out per line referenced as `POS:{order_number}`; generates `POS-####` via `SettingService`; posts balanced journal via `AccountingService::postEntry` (DR per tender, prefers `terminal.petty_cash_account_id` over `pos.cash_account_code` setting for cash; CR `fms.revenue_account_code` net of discount; CR `fms.tax_account_code` when tax > 0). Idempotent on `client_uuid`.
- [x] **PosOrderService::voidOrder**: only paid -> voided; posts compensating in-movements per line via `StockService::recordMovement` type=in, references `POS-VOID:{order_number}`; reverses original journal via `AccountingService::reverseEntry` and stores the result on `void_journal_entry_id`.
- [x] **POS GL account settings** seeded: `pos.cash_account_code` (1100), `pos.card_account_code` (1110), `pos.wallet_account_code` (1120), `pos.cash_over_short_account_code` (5900). Manual tender is GL-mapped to cash drawer.
- [x] **PosShiftSupervisorService::reconcileVariance** (Phase 2.5): variance_pending -> reconciled; posts Cash Over/Short journal (over = DR Cash / CR Cash Over-Short; short = DR Cash Over-Short / CR Cash); records reconciled_by + reconciled_at + variance_journal_entry_id; idempotent on already-reconciled shifts; refuses non-variance_pending statuses. Exposed via `POS /pos/shifts/{shift}/reconcile` (admin-gated on `pos.shift.approve`) and a frontend modal on `/pos/shifts` for variance_pending cards.

---

## C. API Layer & Access Policies (Phase 3 - Shipped)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 1, § 2.B*

- [x] Routes wired under `auth:api` inside `routes/tenant.php`:
  - `GET/POST /pos/terminals`, `GET/PUT/DELETE /pos/terminals/{terminal}` (admin CRUD).
  - `GET /pos/shifts`, `GET /pos/shifts/me` (cashier self), `POST /pos/shifts/open`, `GET /pos/shifts/{shift}`, `POST /pos/shifts/{shift}/close`.
  - `GET /pos/orders` (filters: status / shift_id / terminal_id / cashier_id), `POST /pos/orders` (checkout), `GET /pos/orders/{order}`, `POST /pos/orders/{order}/void`.
- [x] Controllers: `PosTerminalController`, `PosShiftController`, `PosOrderController`. Each authorize() against the matching policy before dispatching to the service.
- [x] Resources (camelCase): `PosTerminalResource`, `PosShiftResource`, `PosOrderResource` + `PosOrderItemResource` + `PosPaymentResource`. Money as float, dates as ISO8601, eager-loaded relations via whenLoaded.
- [x] Policies: `PosTerminalPolicy` (read/write/delete), `PosShiftPolicy` (read/write/approve + cashier-owns-shift fallback on view/update), `PosOrderPolicy` (read/write/void + cashier-owns-order fallback on view). Registered in `TenantServiceProvider::boot`.
- [x] `PosPermissionSeeder` seeds 12 admin perms (`pos.terminal/shift/order/settings.*`) and creates a `cashier` role with the operational subset (`pos.shift.{read,write}` + `pos.order.{read,write}`). Wired into `TenantDatabaseSeeder` after `EcommercePermissionSeeder`.
- [ ] `PosReceiptController` (HTML/PDF receipt rendering) - deferred. Frontend register will produce its own printable view via the thermal-receipt CSS class.

---

## D. Frontend Page Scaffolding & Routing (Phase 4 - Shipped)
*Reference: [`skills/pos/rules.md`](../../skills/pos/rules.md) § 2.C*

- [x] `frontend/composables/usePos.ts` - `terminals` / `shifts` / `orders` namespaces over `useApi()`, full TS types for PosTerminal/PosShift/PosOrder/PosOrderItem/PosPayment + CheckoutPayload, `statusBadgeVariant` helper covering both shift and order FSMs.
- [x] `frontend/pages/pos/terminals.vue` - admin CRUD card grid + status chip filter (CoA pattern), modal form with code/name/warehouse/petty-cash account/location/status/notes, destructive delete via `toast.confirm`.
- [x] `frontend/pages/pos/shifts.vue` - active-shift hero card with "Open register" + "Close shift" CTAs, status chip filter, history grid with opening_float / expected_cash / counted / variance per shift. Open + Close modals.
- [x] `frontend/pages/pos/orders/index.vue` + `[id].vue` - sales list with status chips + search; detail with item table, tender breakdown, void action gated via `toast.confirm`.
- [x] `frontend/pages/pos/register.vue` - touchscreen register: shift indicator bar, product search + grid (barcode-style input + click-to-add), cart panel with qty +/- + remove + live totals, payment modal (4-method picker + tendered + change calculator for cash + reference for card/wallet), post-checkout receipt modal with thermal-receipt CSS (print-only visibility) and Print + New-sale buttons. Idempotent on `client_uuid` per checkout.
- [x] Sidebar gates flipped to `operational: true` for all four POS children in `layouts/default.vue`.

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

## F. Integration & QA Testing (Phase 7 - Shipped)
*Reference: [`skills/pos/testing.md`](../../skills/pos/testing.md) § 1-4*

- [x] `tests/Feature/Tenant/POS/PosTenancyIsolationTest.php` (P0) - cross-tenant blindness for terminals/shifts/orders + asserts `cashier` role seeded with `pos.shift.{read,write}` + `pos.order.{read,write}` but **without** `pos.shift.approve` or `pos.order.void`.
- [x] `tests/Feature/Tenant/POS/PosShiftMutexTest.php` (P1) - terminal mutex (rejects second open shift on same terminal), cashier mutex (rejects second open shift for same cashier on a different terminal), disabled-terminal rejection, reopen-after-close, and the variance math (closing == expected -> closed; closing != expected -> variance_pending).
- [x] `tests/Feature/Tenant/POS/PosCheckoutLifecycleTest.php` (P1) - happy path (stock decrements, journal posted, cash change recorded), idempotent `client_uuid` retries, tender total != grand total rejection, expected_cash math includes cash payments only (card excluded), closed-shift checkout refused.
- [x] `tests/Feature/Tenant/POS/PosVoidLifecycleTest.php` (P1) - void restocks via `POS-VOID:{order}` movement + reverses journal via `void_journal_entry_id`; double-void is idempotent (no double restock); refunded/non-paid orders cannot be voided.
- [ ] **Postman Collections Sync** - deferred to Phase 8.
