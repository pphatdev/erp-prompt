# Feature: Inventory Management & SCM Blueprint

## 1. System Overview
The ERP Inventory & Supply Chain Management (SCM) module provides high-performance warehouse logistics, real-time multi-location tracking, batch/serial lifecycle tracing, and an automated Procure-to-Pay (P2P) workflow. Decoupled from core billing but closely integrated with Sales, FMS (General Ledger), CRM, and eCommerce/POS channels, this module serves as the central product and stock ledger across all divisions.

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              Inventory Management (SCM)                                │
├────────────────────────┬────────────────────────┬─────────────────┬────────────────────┤
│  Warehouse & Stock     │   Procurement (P2P)    │  Optimization   │   eCommerce Sync   │
├────────────────────────┼────────────────────────┼─────────────────┼────────────────────┤
│ - Multi-Warehouse      │ - Supplier Profiles    │ - Low Stock     │ - Real-time Stock  │
│ - Bins & Locations     │ - Purchase Requests    │ - Valuation GL  │ - Order Reserve    │
│ - Stock Ledger (GRN)   │ - Purchase Orders      │ - Cycle Audit   │ - Returns & Restock│
│ - Serial/Batch Trace   │ - Receive & Inspect    │ - Reorder Engine│ - Multi-WH Route   │
└────────────────────────┴────────────────────────┴─────────────────┴────────────────────┘
```

---

## 2. Omnichannel Product Catalog (Single Source of Truth)

The SCM Product Catalog acts as the absolute **Single Source of Truth (SSOT)** for all system products, variants, base prices, costs, and availability. Any selling, estimating, or procurement action in other modules depends entirely on this centralized registry:

### A. Product Types Supported
1.  **Hardware (Physical Stock)**:
    *   Items tracked across warehouses, bins, and physical locations.
    *   Checkouts are strictly subject to physical stock controls and active/reserved checks.
2.  **Software (Digital Entitlements)**:
    *   Products linked directly to backend system modules (e.g. FMS, CRM, HRM).
    *   Buying a software SKU automatically triggers the tenant module activation cascade, allowing immediate access to features. Bypasses physical warehouse logs.

### B. Core Channel Integrations
*   **eCommerce Portal Storefront**: Queries the Inventory Catalog for variant attributes, retail pricing, and cached availability metrics to prevent overselling.
*   **Point of Sale (POS) Terminal**: Direct barcode scanning and SKU lookup query the Inventory database to retrieve active product prices and stock registers instantly.
*   **CRM (Customer Relationship Management)**: B2B Opportunities and product schedules link items directly to the central catalog, allowing sales reps to compile pipeline estimations and projections from current catalog pricing.
*   **Sales Quotation Engine**: Creating a customer Quotation or sales Order pulls the unit price dynamically from `products.unit_price`. Manual edits are audited and restricted via IAM overrides.
*   **Procurement P2P System**: Generating Purchase Orders pulls negotiated cost structures and supplier catalog prices directly from the central SCM repository to ensure billing consistency.

---

## 3. Core Architecture: Enterprise Product Consolidation (SSOT)

> [!IMPORTANT]
> To guarantee enterprise-level data integrity, every module within this ERP system MUST consume products exclusively from the unified Inventory Product database table (`products`). Local, duplicate product tables in other subsystems are strictly prohibited.

```
                  ┌──────────────────────────────┐
                  │ SCM Unified Product Database │
                  │      (products table)        │
                  └──────────────┬───────────────┘
                                 │
         ┌───────────────┬───────┼───────┬───────────────┐
         ▼               ▼       ▼       ▼               ▼
   ┌───────────┐   ┌─────────┐ ┌───┐ ┌───────┐   ┌───────────────┐
   │ eCommerce │   │   POS   │ │CRM│ │ Sales │   │  Procurement  │
   │ Storefront│   │Terminal │ │Pipeline│ │Quotations││Purchase Orders│
   └───────────┘   └─────────┘ └───┘ └───────┘   └───────────────┘
```

### Key Structural Constraints:
1.  **Unified Database Identity**: Every product-related item across the ERP (e.g., Sales Order items, Quotation items, Invoice rows, Subscription lines, CRM Product Schedules, Purchase Order items, and Asset acquisitions) must link directly to `products.id` using a strict database foreign key constraint (`product_id`).
2.  **Unified API Cataloging**: UI components across all modules—including Quotation builders, eCommerce listings, POS checkout lists, and Fleet parts dropdowns—must query SCM's `/api/v1/products` endpoints. Direct local queries that bypass the core module logic are strictly prohibited.
3.  **Real-Time Propagation**: Any changes made in the central Inventory catalog (such as description updates, variant price changes, SKU renaming, or tax-rate adjustments) immediately propagate system-wide in real-time.

---

## 4. Core Functional Specifications

### A. Warehouse & Location Control
*   **Multi-Location Tracking**: Real-time stock levels segmented by branches, physical warehouses, and specific bins/shelves.
*   **Inter-Warehouse Transfers**: Transaction-safe transfers moving stock between origin and destination with a "In-Transit" intermediary status.
*   **Stock Ledger**: Immutable audit trail logging every stock-in, stock-out, and transfer with unique transaction references (GRN, PO, Invoice, Audit).

### B. SKU & Catalog Optimization
*   **SKU Catalog**: Unified catalog supporting variant properties (attributes like color, size, material), software product linkages, and base units of measure (UOM) with conversion matrices (e.g., Box of 12 -> Pieces).
*   **Batch & Lot Tracking**: Expiration date enforcement and lot isolation (critical for food, medical, and high-value hardware).
*   **Traceability**: Serial number logging for high-value assets and warranty claims.

### C. Procure-to-Pay (P2P) Lifecycle
*   **Supplier Directory**: Supplier profiles detailing contacts, lead times, tax configurations, and performance ratings.
*   **Purchase Requisitions (PR)**: Internal department requests for stock/services, routing through eApprovals.
*   **Purchase Orders (PO)**: Binding agreements issued to suppliers. Supports multi-step drafting, approval rules, PDF generation, and automated item price catalog.
*   **Goods Receipt Note (GRN)**: The warehouse receiving terminal where physical goods are checked against a PO, quality inspected, and stock levels are atomically incremented.

### D. Inventory Valuation & GL Integration
*   **FIFO (First-In, First-Out)**: Automatically updates unit cost structures by tracking individual receipt batches.
*   **Weighted Average Cost (WAC)**: Calculates average cost dynamically on every Goods Receipt (GRN).
*   **FMS Accounting Handshake**: Stock-In postings trigger debit to Inventory Asset and credit to Accounts Payable (or AP Accrual). Stock-Out for sales triggers debit to Cost of Goods Sold (COGS) and credit to Inventory Asset.

---

## 5. eCommerce Module Integration
To drive high-performance digital commerce, the eCommerce module integrates natively with the Inventory core via highly optimized, cached stock endpoints and atomic transaction boundaries:

*   **Real-time Stock Availability**: The online storefront queries available quantities (aggregated across physical warehouses or pinned to a specific regional warehouse) prior to rendering cart pages, preventing out-of-stock checkouts.
*   **Stock Reservation Engine**: During checkout, the system places a temporary, time-restricted **Stock Reservation** (locked for 15 minutes). This prevents overselling during high-concurrency flash sales. If payment fails or checkout is abandoned, the reservation releases automatically back to the active pool.
*   **Order Fulfillment & Stock-Out**: Once payment is completed, the reservation is committed to the immutable Stock Ledger as a definitive stock-out movement, referencing the eCommerce Order ID.
*   **Returns & Quarantine Restocking**: Returned eCommerce items (`ecom-refunds`) undergo quality checks before restocking. Damaged items route to a quarantine warehouse, while good items trigger a stock-in adjustment to release them for future eCom orders.
*   **Multi-Warehouse Intelligent Routing**: eCommerce orders automatically route to the optimal warehouse based on proximity rules, shipping rate optimization, and stock availability.

---

## 6. Existing vs. Planned Feature Status

| Feature | Sub-component | Backend API Status | Frontend UI Status |
| :--- | :--- | :--- | :--- |
| **Products & Variants** | SKU + Variants CRUD; software ↔ module pivot | ✅ Shipped | ✅ Shipped (`/inventory/products`) |
| **Categories** | Hierarchical tree (self-FK), cycle + dependency guards, archive protection | ✅ Shipped | ✅ Shipped (`/inventory/categories`) |
| **Warehouses** | Multi-warehouse CRUD; archive blocked by on-hand stock | ✅ Shipped | ✅ Shipped (`/inventory/warehouses`) |
| **Suppliers** | Profiles + rating + lead-time + payment terms; archive blocked by open POs | ✅ Shipped | ✅ Shipped (`/inventory/suppliers`) |
| **Stock Ledger** | Movement ledger (`in` / `out` / `transfer` / `adjustment`) | ✅ Shipped (`StockService::recordMovement`) | ❌ Planned (ledger UI) |
| **Stock Transfers** | Dispatch → receive FSM | ✅ Shipped | ◐ Partial UI |
| **Procurement (P2P)** | PR → PO → eApprovals → GRN; full FSM `draft→submitted→approved→receiving→received` | ✅ Shipped | ✅ Shipped (`/inventory/purchase-orders` + `/create` wizard + `/{id}` detail/receive) |
| **Costing** | Weighted Average Cost on receive | ✅ Shipped (`ProcurementService::receive`) | n/a |
| **Costing** | FIFO option | ❌ Planned | n/a |
| **Optimization** | Low-stock detection + reorder suggestions | ✅ Shipped (`LowStockDetected` event) | ❌ Planned (dashboard KPI + alert list) |
| **eCommerce Sync** | 15-min cart reservation + returns restock | ◐ Backend (INV-RESERVE + INV-DAEMON) shipped; storefront pending | ❌ Planned |
| **Omnichannel Pricing** | SSOT for Quotations / CRM / PO / POS | ❌ Planned | ❌ Planned |

---

## 7. Premium UX & Design Aesthetics

### A. Warehouse Stock Board
A sleek, grid-based dashboard with terminal-like speed.
*   **KPI Cards**: Total SKUs, Low-Stock items (vibrant red/amber accent), Total Valuation, Pending Receipts.
*   **Active Warehouses Grid**: Card deck showing individual warehouse metrics (capacity indicators, active transfers, bin counts).
*   **Barcode Scanner Fast-Track**: Sticky top action bar for warehouse workers to instantly log a receipt or scan a bin code.

### B. Interactive Procure-to-Pay (PO) Wizard
*   **Supplier Select**: Dropdown with instant lead-time and performance badge.
*   **Line Items Grid**: PrimeVue editable table allowing quick SKU addition, auto-suggesting negotiated supplier prices, and live tax/total calculations.
*   **Activity/Approvals Sidebar**: Interactive visual timeline showing approval progress (Draft -> Pending Manager -> Approved -> Sent to Supplier -> Received).
