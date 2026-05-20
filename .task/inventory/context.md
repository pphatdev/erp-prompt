# Feature Context: Inventory Management (Backend)

Implementation phases for the Inventory (SCM) module, focusing on multi-location tracking and procurement.

## Implementation Phases (Backend Only)

### Phase 1: Core Inventory Schema
- [x] Create migrations for `warehouses`, `suppliers`, `products`, and `stock_movements`.
- [x] Implement models with `BelongsToTenant` and `Auditable`.

### Phase 2: Stock Management Engine
- [x] Implement `StockService` for handling stock-in, stock-out, and transfers.
- [x] Ensure atomic database transactions for all stock movements to prevent negative balances.
- [x] Support calculation of current stock level based on movement aggregation.

### Phase 3: Procurement Flow
- [ ] Implement `ProcurementService` for managing Suppliers and Purchase Orders.
- [ ] Create logic for low-stock alerts and automated reorder suggestions.

### Phase 4: API & Access Control
- [x] Create `WarehouseController`, `ProductController`, and `StockMovementController`.
- [x] Implement `ProductResource` and `StockMovementResource`.
- [x] Define `inventory.warehouse.*`, `inventory.procurement.*`, and `inventory.suppliers.*` permission policies.

### Phase 5: QA & Integrity Testing
- [x] P0 Tenancy Isolation tests.
- [x] P1 Concurrency tests for stock movements (preventing race conditions leading to negative stock).

