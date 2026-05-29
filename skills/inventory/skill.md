---
name: inventory-management
description: Products, variants, categories, warehouses, suppliers, stock movements, and the Purchase Order (P2P) lifecycle.
---
# Inventory Management

Use this skill when building or extending product / catalog / stock / procurement features.

## Module surface (shipped)

```
Inventory (sidebar group)
├── Products              — CRUD + variants + module-linking for software products
├── Categories            — hierarchical tree with cycle/dependency guards
├── Warehouses            — CRUD + KPI cards + on-hand-stock guard
├── Suppliers             — directory with rating / lead-time / payment terms
└── Purchase Orders       — full FSM (draft→submitted→approved→receiving→received) + WAC on receive
```

| Layer | Path |
|---|---|
| Controllers | `app/Tenants/Modules/Inventory/Controllers/{Product,ProductVariant,Category,Warehouse,Supplier,StockMovement,StockTransfer,PurchaseOrder,Procurement}Controller.php` |
| Services | `app/Tenants/Modules/Inventory/Services/{Product,ProductVariant,Category,Warehouse,Supplier,Stock,Procurement}Service.php` |
| Resources | `app/Tenants/Modules/Inventory/Resources/*.php` |
| Events | `app/Tenants/Modules/Inventory/Events/{LowStockDetected, StockReceived, ...}.php` |
| Listeners | `app/Tenants/Modules/Inventory/Listeners/*.php` |
| Models | `app/Models/Tenant/{Product, ProductVariant, Category, Warehouse, Supplier, StockMovement, StockTransfer, PurchaseOrder, PurchaseOrderItem}.php` |
| Policies | `app/Policies/{Product,Category,Warehouse,Supplier,PurchaseOrder}Policy.php` |
| Migrations | `database/migrations/tenant/{date}_create_inventory_tables.php` + later additions for categories, POs, low-stock thresholds |
| Permission seeder | `database/seeders/InventoryPermissionSeeder.php` |
| Pages | `frontend/pages/inventory/{products, categories, warehouses, suppliers, purchase-orders}` |
| Composables | `frontend/composables/useInventory.ts` (wraps `useApi()` for every Inventory namespace) |

## Routes (in `routes/tenant.php`, inside `auth:api`)

```
GET|POST|PUT|DELETE   /products
GET|POST|PUT|DELETE   /products/{product}/variants     ← nested
GET|POST|PUT|DELETE   /product-variants                 ← shallow
GET                    /categories?tree=1
GET|POST|PUT|DELETE   /categories
GET|POST|PUT|DELETE   /warehouses
GET|POST|PUT|DELETE   /suppliers
GET                    /stock-movements
POST                   /stock-transfers
POST                   /stock-transfers/{stockTransfer}/dispatch
POST                   /stock-transfers/{stockTransfer}/receive
GET|POST|PUT|DELETE   /purchase-orders
POST                   /purchase-orders/{purchaseOrder}/submit
POST                   /purchase-orders/{purchaseOrder}/approve
POST                   /purchase-orders/{purchaseOrder}/receive
```

## Permission slugs (seeded by `InventoryPermissionSeeder`)

```
inventory.product.{read,write,delete}
inventory.product_variant.{read,write,delete}
inventory.category.{read,write,delete}
inventory.warehouse.{read,write,delete}
inventory.supplier.{read,write,delete}
inventory.stock.{read,write}
inventory.po.{read,write,delete,approve}
```

## Core domain rules

### 1. Catalog is the Single Source of Truth (P0)
Every product reference across the ERP (Sales Order items, Quotation items, Invoice rows, Subscription lines, CRM Product Schedules, PO items) FKs to `products.id`. Do **not** introduce per-module shadow product tables. Frontend dropdowns hit `GET /products`, not local lists.

### 2. Two product types
- **`hardware`** — physical stock; movements tracked in `stock_movements`; warehouse-aware.
- **`software`** — digital entitlement; linked to a system module via the `product_modules` pivot (`pivot.module_id` → `modules.id`). Purchasing a software SKU during tenant provisioning triggers `expandEntitledSlugs()` which cascades parent→children module activation.

Field: `products.product_type` enum.

### 3. Categories
- Self-referential tree (`parent_id` → `categories.id`). Migrated using the split `Schema::create` + `Schema::table` pattern (see `rules/structure/skill.md` § Self-FK gotcha).
- `CategoryService` enforces: no self-parent, no cycles, no move-under-descendant, no archive when children or products exist.
- `tree()` endpoint returns the eager-loaded forest; pages render via a recursive `CategoryNode` component.
- Slug auto-generated in the model's `creating` boot hook if empty.

### 4. Warehouses + Suppliers
- Both have **archive guards**: warehouse blocked while on-hand stock > 0; supplier blocked while open POs exist.
- Frontend: `/inventory/warehouses` and `/inventory/suppliers` show KPI cards + a list with kebab row-actions.

### 5. Purchase Order FSM
```
draft → submitted → approved → receiving → received
   ↘ cancelled (terminal at draft|submitted)
```
- `submit` routes the PO to **eApprovals** (see `skills/eapprovals/`). Approval action moves the PO to `approved`.
- `receive` increments warehouse stock atomically and updates the product's average cost using **Weighted Average Costing**:
  - `new_avg = (old_qty * old_cost + received_qty * received_cost) / (old_qty + received_qty)`
- All transitions wrapped in `DB::transaction`.
- Numbering: PO number generated by `ProcurementService::generatePoNumber()` reading `numbering.po_prefix` from `SettingService` — see [`skills/configuration/numbering.md`](../configuration/numbering.md).

### 6. Stock movements ledger
- Append-only. Recorded by `StockService::recordMovement()` and `StockService::transferStock()`.
- Every movement carries `type` (`in` / `out` / `transfer` / `adjustment`), `reference_type` + `reference_id` (PO, Order, manual), `warehouse_id`, `product_id`, `quantity`, `unit_cost`.
- Low-stock detection fires `LowStockDetected` event when on-hand drops below `products.reorder_threshold`.

## Frontend specifics

- **Pages**: hand-rolled Tailwind tables + custom modals (`.glass-card`, `fixed inset-0 bg-black/50 backdrop-blur-sm`). PrimeVue used only for the PO line-items editor (sortable rows).
- **Categories page** is the canonical recursive-render example — see [`rules/structure/skill.md`](../../rules/structure/skill.md) which uses it as the worked-example.
- **Module gating**: every sidebar entry has `moduleSlug: 'inventory'`; if the tenant disables Inventory, the entire group disappears.
- **Row actions**: ≥ 2 actions per row collapse into a single kebab trigger (see `rules/frontend/standards.md` § 7).

## Status table (shipped vs planned)

| Feature | Backend | Frontend |
|---|:---:|:---:|
| Products + variants CRUD | ✅ | ✅ (`/inventory/products`) |
| Categories (tree, cycle guard, archive guard) | ✅ | ✅ (`/inventory/categories`) |
| Warehouses + on-hand guard | ✅ | ✅ (`/inventory/warehouses`) |
| Suppliers + open-PO guard | ✅ | ✅ (`/inventory/suppliers`) |
| Stock movements ledger | ✅ | ❌ (ledger view planned) |
| Stock transfers (dispatch + receive) | ✅ | ◐ |
| Purchase Orders FSM + eApprovals routing | ✅ | ✅ (`/inventory/purchase-orders` + `/create` wizard + `/{id}` detail) |
| Weighted Average Costing on receive | ✅ | n/a |
| Low-stock alert + reorder suggestions | ✅ | ❌ |
| FIFO costing option | ❌ | ❌ |
| eCommerce stock reservation (15-min TTL) + returns restock | ◐ (INV-RESERVE shipped) | ❌ (storefront pending) |
| Omnichannel pricing SSOT for Quotations/CRM/PO/eCommerce/POS | ❌ | ❌ |

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Category create fails "parent does not exist" | Parent FK pointed at a soft-deleted row | Restore or use a non-archived parent |
| `archive blocked: category has children` | Tried to archive a non-leaf | Move/archive children first, then the parent |
| Warehouse archive blocked | On-hand stock > 0 | Transfer stock out first |
| Supplier archive blocked | Open POs | Cancel or complete the POs first |
| PO `submit` returns 422 "no approval workflow" | eApprovals workflow not seeded for `purchase_order` | Re-run `TenantDatabaseSeeder` (seeds default workflows) |
| Receive doesn't update product cost | `WAC` path skipped because `received_qty <= 0` | Ensure all line items have positive received quantity |
| Self-FK migration error on Postgres | Single `Schema::create` with FK inline | Split into `Schema::create` + `Schema::table` |

## Read next
- [`overview.md`](./overview.md) — full architectural diagram + omnichannel intent
- [`rules.md`](./rules.md) — detailed FSM + valuation algorithms
- [`flow.md`](./flow.md) — P2P Mermaid flows
- [`testing.md`](./testing.md) — P0/P1/P2 test matrix
- [`rules/structure/skill.md`](../../rules/structure/skill.md) — Inventory Categories is the worked example
