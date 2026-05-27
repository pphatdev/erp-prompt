# Feature Context: Full-Stack Inventory & SCM Roadmap

Implementation phases for the multi-tenant Enterprise Inventory and Supply Chain Management (SCM) system, spanning robust backend transactional engines and sleek, interactive PrimeVue frontends, now fully unified with the **eCommerce, POS, CRM, and Sales** modules under an omnichannel Single Source of Truth (SSOT) catalog.

---

## 📅 Full-Stack Implementation Phases

### Phase 1: Core Schema & Models (Backend)
- [x] Create core migrations for `warehouses`, `suppliers`, `products`, and `stock_movements` with tenant indexes.
- [x] Implement models (`Warehouse`, `Supplier`, `Product`, `ProductVariant`, `StockMovement`) with `BelongsToTenant` and `Auditable` traits.
- [x] Seed standard module permissions and register in tenant seeder.

### Phase 2: Stock Engine & Valuation Engine (Backend)
- [x] Implement `StockService` with transaction boundaries for `recordMovement` and `transferStock`.
- [x] Prevent negative balances unless explicitly enabled by tenant config.
- [ ] Implement `ValuationService` supporting WAC (Weighted Average Costing) and FIFO calculation.
- [ ] Implement stock availability query by Warehouse and Bin.
- [ ] Implement `getNetAvailableStock` method deducting active reservations for eCommerce and POS terminals.

### Phase 3: Procurement & Omnichannel Price Sourcing (Backend)
- [ ] Create schema migrations for `purchase_orders` and `purchase_order_items`.
- [ ] Implement `ProcurementService` managing Supplier profiles and Purchase Order life-cycle.
- [ ] Build Goods Receipt Note (GRN) endpoint to increment stock upon PO receipt.
- [ ] Implement eCommerce Stock Reservation mechanism with a 15-minute TTL constraint.
- [ ] Integrate central pricing check in CRM (Opportunity product schedules) and Sales (Quotation and Invoice lines), restricting overrides without permissions.
- [ ] Build SCM background daemon (`StockReservationDaemon`) to auto-sweep expired reservations.
- [ ] Build Low-Stock Notification engine (background job tracking thresholds).

### Phase 4: API Surface & Access Policies (Backend)
- [x] Implement `ProductController` & `StockMovementController` with pagination envelopes.
- [ ] Implement `WarehouseController` and `SupplierController` for admin CRUD.
- [ ] Implement `PurchaseOrderController` and `GoodsReceiptController` for procurement.
- [ ] Expose public endpoints `/products/ecom-availability` for fast, cached storefront stock checks.
- [ ] Define and wire authorization Policies (`WarehousePolicy`, `SupplierPolicy`, `PurchaseOrderPolicy`, `ProductPricePolicy`).

### Phase 5: Warehouse & Supplier Management UI (Frontend)
- [ ] Build `/pages/inventory/warehouses.vue` — PrimeVue grid with search, edit dialog, and stock counts.
- [ ] Build `/pages/inventory/suppliers.vue` — Supplier directory showing contact information, active POs, and ratings.
- [ ] Integrate barcode scanner utility for quick bin lookup and mobile receipt entry.

### Phase 6: Interactive Procurement & eCommerce/POS Sync UI (Frontend)
- [ ] Build `/pages/inventory/purchase-orders/index.vue` — Detailed listing of POs with status badges.
- [ ] Build `/pages/inventory/purchase-orders/create.vue` — A multi-step PO wizard with supplier auto-pricing and line-item grid.
- [ ] Build SCM dashboard section for eCommerce & POS Sync status, mapping active online cart reservations and listing quarantine return metrics.

### Phase 7: Stock Ledger, Transfers & Adjustments (Frontend)
- [ ] Build `/pages/inventory/stock-movements.vue` — Live ledger listing all movements with query filters.
- [ ] Build Transfer Modal — Safe interface supporting transfers with real-time source-stock verification.
- [ ] Build Cycle Count reconciliation sheet mapping inventory variance.

### Phase 8: QA Automation & Omnichannel E2E Testing
- [x] Implement Pest P0 Tenancy Isolation tests.
- [ ] Implement Pest P0 eCommerce/POS stock reservation and background release tests.
- [ ] Implement Pest P1 Pricing Integrity and IAM Price Override Enforcement tests.
- [ ] Implement Pest P1 Concurrency/Race Condition tests for stock transfers.
- [ ] Build Vitest and Playwright test suites for E2E P2P and Checkout flow.
