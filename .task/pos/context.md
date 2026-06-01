# Feature Context: Point of Sale (POS)

Detailed implementation phases for the POS (Point of Sale) module, covering the full checkout lifecycle, offline resiliency, cashier shift controls, and double-entry FMS/Inventory cuts.

## Implementation Phases

### Phase 1: Core Database Scaffolding
- [ ] Create database migration for `pos_terminals`, `pos_shifts`, `pos_orders`, `pos_order_items`, and `pos_payments`.
- [ ] Establish primary key UUID boot triggers, SoftDeletes, and Auditable traits on all models.
- [ ] Import and verify multi-tenant connection scoping via `BelongsToTenant`.

### Phase 2: Cashier Shifts & Drawer Controls
- [ ] Implement `PosShiftService` to handle `openShift()`, skims, and `closeShift()`.
- [ ] Build math validators resolving shift closing counts and calculating cash discrepancies.
- [ ] Create supervisor variance approvals override mechanisms.

### Phase 3: High-Speed Checkout & Stock-Out Engines
- [ ] Implement `PosOrderService::checkout()` wrapped in database transaction blocks.
- [ ] Call `InventoryService::recordMovement()` to deduct stock from registers' target warehouses using WAC calculations.
- [ ] Wire FMS ledger postings calling `AccountingService::postEntry()` to write balanced journal debits/credits.

### Phase 4: Client-Side Offline Caching & Sync Daemons
- [ ] Build IndexedDB database tables caching product catalogs, prices, and barcodes.
- [ ] Create local checkout queue serializing carts and payments locally when offline.
- [ ] Implement background heartbeat daemon and sync router `POST /api/v1/pos/sync-offline-orders` executing idempotent client UUID deduplication.

### Phase 5: Front-Counter Touch Viewport (Nuxt 3)
- [ ] Design touch-optimized register grid with "Quick Pick" panels and fast search bars.
- [ ] Integrate thermal print CSS wrappers hiding standard nav components for 80mm receipt generation.
- [ ] Build multi-cart suspensions (Parking) and split payment overlays.

### Phase 6: QA Testing & API Collections
- [ ] Create Pest integration test suites checking isolation, WAC cost allocations, and offline dedupe rules.
- [ ] Configure Postman automated collections verifying shift, checkout, and close lifecycles.
