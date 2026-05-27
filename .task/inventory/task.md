# Task Tracker: Inventory & SCM System

## 🎯 Objective
Upgrade the core Inventory system to support multi-location warehouse management, automated Procure-to-Pay (P2P) workflows, low-stock triggers, WAC valuation, and detailed PrimeVue analytics interfaces, with complete integration hooks for **eCommerce, POS, CRM, and Sales** pricing and stock reservations under a Single Source of Truth (SSOT).

---

## 📋 Full-Stack Checklist

### Phase 1: Core SCM Schema & Model Setup
- [x] Initial migrations for `warehouses`, `suppliers`, `products`, `stock_movements`
- [x] Set up Eloquent models with `BelongsToTenant` and `Auditable` traits
- [x] Establish seeders for initial `inventory` module permissions
- [x] **INV-CATEGORY** — Migration `000061_create_categories_and_product_category` creates hierarchical `categories` table (self-FK parent_id, color, sort_order) + adds nullable `products.category_id` FK. `Category` model with parent/children/products relations. `CategoryService` (slug auto-derive, move-under-descendant cycle guard, archive blocked by children/products), `CategoryController` (flat + `?tree=1`), `CategoryResource` (productsCount via `withCount`), `CategoryPolicy` (`inventory.category.{read,write,delete}` perms seeded). Routes: `apiResource('categories')`.
- [x] **INV-VARIANT-CRUD** — `ProductVariantController` (nested + shallow), `ProductVariantService` (cross-table SKU uniqueness with `products`), `ProductVariantPolicy` reusing `inventory.product.*`. Routes: `apiResource('products.variants')->shallow()` → POST `/products/{p}/variants`, PUT/DELETE `/variants/{v}`.

### Phase 2: Stock Engine & Valuation
- [x] Write transaction-safe `StockService` (recordMovement and transfers)
- [x] Implement negative-stock validation guards
- [x] **INV-WAC** — Migration `000057_add_wac_columns_to_products` (total_quantity / average_cost / last_cost). `StockService::recordMovement` recomputes WAC inline; `ProcurementService::receive` threads PO-line `unit_cost`. `ProductResource` exposes the trio. Pest `WeightedAverageCostingTest` covers first-receipt anchor, cumulative averaging across 3 receipts, out-movement preserves cost basis, no-unit-cost ingress preserves WAC, full PO→receive integration.
- [ ] **INV-TRANSFER-UI** — polish `transferStock`: source-warehouse lockForUpdate, in-transit reference, `TransferRecord` audit row, `POST /stock-movements/transfer` endpoint.
- [ ] `getNetAvailableStock(product, warehouse) = physical - active_reservations` — depends on INV-RESERVE.

### Phase 3: Procurement & Omnichannel Price Integration (Backend)
- [x] **Migration `000053_create_purchase_orders_tables`** — `purchase_orders` + `purchase_order_items` with status FSM (draft/submitted/approved/receiving/received/cancelled), snapshot fields, per-line `received_qty` tracking, FKs to suppliers + warehouses + products + variants.
- [x] **`PurchaseOrder` + `PurchaseOrderItem` models** — status constants, `OPEN_STATUSES`, `isCancellable()`/`isReceivable()`/`isFullyReceived()`/`outstandingQty()` helpers.
- [x] **`ProcurementService`** — `createDraft`/`addItem`/`submit`/`approve`/`receive`/`cancel` with per-line over-receipt guard, auto status flip (receiving→received), stock-in posting via existing `StockService::recordMovement`, transactional with `lockForUpdate` during receive.
- [x] **`SupplierService::archive`** now blocks when any open PO references the supplier.
- [x] **REST surface** — `apiResource('purchase-orders')` + `POST /{id}/submit|approve|receive|cancel`; controller with search/status/supplier/warehouse filters.
- [x] **`PurchaseOrderPolicy`** — uses `inventory.procurement.{read,write,delete}` + separate `approve` gate (`inventory.procurement.approve`). Registered in `TenantServiceProvider`.
- [x] **Pest `ProcurementLifecycleTest`** — 7 cases covering draft snapshot/totals, full lifecycle, partial receipts, over-receipt rejection, cancel guards, supplier-archive blocked by open PO.
- [x] **INV-RESERVE** — Migration `000058_create_stock_reservations_table` + `StockReservation` model. `StockReservationService::reserve/commit/cancel/expireDue` with 15-min default TTL, lockForUpdate on Product row so concurrent reserves serialise. `StockService::getPhysicalStock` + `getNetAvailableStock(product, warehouse) = physical − active_reservations`. REST: `apiResource('stock-reservations')` + commit/cancel + `GET /stock-reservations/availability?product_id=&warehouse_id=`. Permissions `inventory.reservations.{read,write,commit}` seeded; `StockReservationPolicy` registered. Pest `StockReservationTest` covers reserve/over-reserve/commit/cancel/idempotent re-commit/expire-due/zero-qty rejection.
- [x] **INV-DAEMON** — console command `inventory:expire-reservations` flips active→expired when `expires_at < now`; per-tenant fan-out; scheduled every 2 min.
- [x] **INV-LOWSTOCK** — `ProductWentBelowMinimumStock` event dispatched after `StockService::recordMovement` when crossing threshold downward; `LogLowStockAlert` listener writes audit + queues notification; anti-spam guard.
- [ ] **INV-PRICING** — Catalog pricing SSOT: `quotation_items.catalog_unit_price` + `order_items.catalog_unit_price` so the UI can flag manual overrides; `sales.quotations.override_price` permission gating line-level edits.
- [x] **INV-APPROVALS** — `ProcurementService::submit` dispatches `ApprovalRequested(PurchaseOrder)`; PO promotes to `approved` only via `ApprovalRequestFinalized` listener; threshold from `fms.po_approval_threshold` setting.

### Phase 4: SCM Rest API Surfaces
- [x] Register API routes for `products` and `stock-movements`
- [x] **Warehouses CRUD** — migration `000052_extend_warehouses_and_suppliers` (manager/address/capacity/active/notes), `WarehouseService` (create/update/archive/stockByProduct + on-hand guard), `WarehouseController` (search + is_active filter), `WarehouseResource`, `apiResource('warehouses')`, `WarehousePolicy`, Pest `WarehouseCrudTest`.
- [x] **Suppliers CRUD** — same migration (code unique/contact/payment_terms/lead_time/rating/active), `SupplierService` (rating-range guard, P2P-archive placeholder), `SupplierController` (search + active + min_rating filters), `SupplierResource`, `apiResource('suppliers')`, `SupplierPolicy`, Pest `SupplierCrudTest`.
- [x] Purchase Orders endpoints — covered by P2P phase 3 work above (`apiResource('purchase-orders')` + transition POSTs).
- [ ] **INV-STOREFRONT** — `GET /api/v1/storefront/products` + `GET /api/v1/storefront/products/{sku}/availability`. Cached 60s, tenant-scoped via header. New permission `inventory.storefront.read` + `storefront_consumer` role for headless integrations. Depends on INV-RESERVE.
- [ ] FormRequest classes for the heavier endpoints (Warehouses + Suppliers controllers still inline-validate — extract once a field count crosses ~10).

### Phase 5: Warehouse & Supplier Management UI (Frontend)
- [x] **Warehouses page** — `frontend/pages/inventory/warehouses.vue` (search + active filter, KPI cards, create/edit modal, archive with on-hand guard surfaced via toast).
- [x] **Suppliers directory** — `frontend/pages/inventory/suppliers.vue` (search/active/min-rating filters, rating stars + lead-time + payment-terms cards, create/edit modal, archive blocked-by-open-PO error surfaced via toast).
- [ ] Integrate camera/barcode scanning library for SKU lookup and POS input

### Phase 6: P2P Procurement & eCommerce/POS Sync UI (Frontend)
- [x] **Purchase Orders list** — `frontend/pages/inventory/purchase-orders/index.vue` (status/supplier/warehouse filters, KPI cards, status badges, inline submit/approve/cancel actions gated by FSM + permission).
- [x] **PO Wizard** — `frontend/pages/inventory/purchase-orders/create.vue` (supplier+warehouse selects, line-item grid with product+variant picker, unit-cost prefill from catalog, save-draft vs save+submit).
- [x] **PO detail / GRN receive** — `frontend/pages/inventory/purchase-orders/[id].vue` (FSM action header, per-line receive inputs, "receive all outstanding" helper, receipt notes, breadcrumb override to PO number).
- [x] **useInventory composable + types** — `frontend/composables/useInventory.ts`, `frontend/types/inventory.ts` (warehouses/suppliers/purchaseOrders/catalogue namespaces, `poStatusBadgeVariant` mapping).
- [x] **Sidebar nav** — new "Inventory" group in `layouts/default.vue` gated by `inventory` module + `inventory.{warehouse,suppliers,procurement}.{read,write}` permissions. `ModuleSeeder` extended with `inventory` + 3 child entries.
- [ ] Build eCommerce/POS Sync Dashboard showing active cart reservations and quarantine return rates

### Phase 7: Stock Transfers & Audit Ledger (Frontend)
- [ ] Implement global Stock Movement ledger page with reference linking
- [ ] Build safe Inter-Warehouse transfer modal with origin balance lock
- [ ] Build Cycle Count reconciliation sheet mapping inventory variance

### Phase 8: QA & Security Assurance
- [x] Pest tests checking multi-tenant stock boundary isolation
- [x] Pest `ProcurementLifecycleTest` (covers basic PO→GRN→stock-in cycle).
- [ ] **INV-TESTS** — concurrency tests for reservations + transfers; E2E happy path PO→GRN→Invoice; SCM pricing override authorization. Depends on INV-RESERVE + INV-PRICING.
- [ ] Pest tests verifying eCommerce/POS reservation lifecycle (TTL lock, background release, checkout commit) — bundled in INV-RESERVE.
