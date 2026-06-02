# Feature Context: Point of Sale (POS)

Detailed implementation phases for the POS (Point of Sale) module, covering the full checkout lifecycle, offline resiliency, cashier shift controls, and double-entry FMS/Inventory cuts.

## Implementation Phases

### Phase 1: Core Database Scaffolding
- [ ] Create database migration for `pos_terminals`, `pos_shifts`, `pos_orders`, `pos_order_items`, and `pos_payments`.
- [ ] Establish primary key UUID boot triggers, SoftDeletes, and Auditable traits on all models.
- [ ] Import and verify multi-tenant connection scoping via `BelongsToTenant`.

### Phase 2: Cashier Shifts & Drawer Controls (Shipped 2026-06-02)
- [x] `PosTerminalService` (create/update/disable/destroy with open-shift + order-history guards).
- [x] `PosShiftService::openShift` (per-cashier and per-terminal mutex via lockForUpdate).
- [x] `PosShiftService::closeShift` (expected_cash from sum of cash payments on PAID orders; variance computed; transitions to closed or variance_pending).
- [x] `PosShiftService::activeShiftForCashier` for register dashboard.
- [ ] Supervisor variance reconcile deferred to Phase 2.5.

### Phase 3: High-Speed Checkout & Stock-Out Engines (Shipped 2026-06-02)
- [x] `PosOrderService::checkout` atomic in `DB::transaction`.
- [x] `StockService::recordMovement` type=out per line referenced as `POS:{order_number}`.
- [x] Balanced journal via `AccountingService::postEntry`: tender DRs (terminal petty cash overrides cash code), CR revenue net of discount, CR sales tax payable when tax > 0.
- [x] `client_uuid` idempotency for offline-replay safety.
- [x] `PosOrderService::voidOrder` compensating in-movements + journal reversal.
- [x] Setting defaults seeded: `pos.cash_account_code`, `pos.card_account_code`, `pos.wallet_account_code`.

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
