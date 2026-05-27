# Feature Rules: Inventory Management & SCM

## 1. Identity & Access Control (IAM Integration)
Permissions are enforced strictly at the Controller level using the standard `module.feature.action` pattern.

### Feature Matrix & Access Keys:
- **Module Slug**: `inventory`
- **Permissions**:
  | Feature | Read | Write | Delete | Special Action |
  | :--- | :--- | :--- | :--- | :--- |
  | **Warehouse** | `inventory.warehouse.read` | `inventory.warehouse.write` | `inventory.warehouse.delete` | - |
  | **Product Catalog** | `inventory.product.read` | `inventory.product.write` | `inventory.product.delete` | `inventory.product.export` |
  | **Stock Ledger** | `inventory.stock.read` | `inventory.stock.write` | - | `inventory.stock.adjust` (Manual Audit) |
  | **Suppliers** | `inventory.suppliers.read` | `inventory.suppliers.write` | `inventory.suppliers.delete` | - |
  | **Procurement** | `inventory.procurement.read` | `inventory.procurement.write` | `inventory.procurement.delete` | `inventory.procurement.approve` |
  | **eCommerce Sync** | `inventory.ecommerce.read` | `inventory.ecommerce.write` | - | `inventory.ecommerce.reserve` |

---

## 2. Multi-Tenant Scoping Rules (P0)
1.  **Isolation Guard**: All Inventory, Warehouses, Suppliers, Stock Movements, and eCommerce links MUST use the `BelongsToTenant` trait.
2.  **Shared-Nothing Databases**: No database queries may cross tenant schemas.
3.  **Tenant Storage**: Product images, catalog sheets, and PO PDFs must be stored in the tenant's isolated directory: `storage/tenants/{tenant_handle}/products/` or `storage/tenants/{tenant_handle}/procurement/`.

---

## 3. Stock Level & Movement Rules

### A. Atomicity & Concurrency (P0)
1.  **Strict Locking**: When recording stock movements (or during transfers), the product row MUST be locked using `lockForUpdate()` before assessing available levels to prevent double-allocation/race conditions.
2.  **Transaction Boundary**: Inter-warehouse transfers must occur within a single database transaction. If the decrement at Origin fails or the increment at Destination fails, the entire transaction rolls back immediately. No partial movements are allowed.
3.  **Negative Stock Restriction**: Unless the tenant's setting `allow_negative_stock` is explicitly set to `true`, the system MUST throw an `InsufficientStockException` if an outward movement or transfer exceeds available stock at that specific warehouse.

### B. Valuation Ledger (FIFO vs WAC)
Every stock movement records the unit cost of the transaction to facilitate financial reporting.
1.  **Weighted Average Costing (WAC) (Default)**:
    *   Formulas applied on every inward Goods Receipt (GRN):
        $$\text{New Average Cost} = \frac{(\text{Current Qty} \times \text{Current WAC}) + (\text{Received Qty} \times \text{Purchase Cost})}{\text{Current Qty} + \text{Received Qty}}$$
    *   This updated cost is stored as the product's `unit_cost` and mapped to future COGS entries.
2.  **First-In, First-Out (FIFO)**:
    *   Maintains a stack of stock receipt records (`stock_movements` with `type = 'in'`).
    *   Outward movements consume this stack sequentially, calculating COGS based on the actual purchase cost of the oldest available batch.

---

## 4. Procurement (P2P) State Machine
All Purchase Orders (POs) flow through a rigid state-machine. State changes must trigger corresponding business actions:

```
    ┌──────────┐      Submit       ┌──────────────────┐
    │  Draft   ├──────────────────>│ Pending Approval │
    └────┬─────┘                   └────────┬─────────┘
         │                                  │
         │ Cancel                           │ Approve
         ▼                                  ▼
    ┌──────────┐      Revoke       ┌──────────────────┐
    │Cancelled │<──────────────────┤     Approved     │
    └──────────┘                   └────────┬─────────┘
                                            │
                                            │ Sent to Supplier
                                            ▼
    ┌──────────┐      GRN Recv     ┌──────────────────┐
    │  Closed  │<──────────────────┤       Sent       │
    └──────────┘                   └──────────────────┘
```

### Action Rules:
*   **Draft**: Editable by procurement staff. No financial/stock commitments are made.
*   **Pending Approval**: Locked for edits. Submitted to approval workflow queue.
*   **Approved**: Authorized to purchase. A PDF version is signed and made available.
*   **Sent**: Dispatched to vendor. Tracks supplier lead time.
*   **GRN Partially Received / Fully Received**:
    *   Creating a Goods Receipt Note (GRN) links to the PO.
    *   Partially received POs remain open with status `partially_received`.
    *   Fully received POs automatically transition to `received` / `closed`.
*   **FMS Posting Rules**:
    *   On GRN validation, post to GL Journal:
        *   **DEBIT**: Inventory Asset Account (Asset)
        *   **CREDIT**: AP Accrual Account (Liability)

---

## 5. Low-Stock Alerts Engine
*   **Calculation Hook**: After any stock-out or transfer-out movement is recorded, a background job calculates:
    $$\text{Available Stock} = \sum(\text{Quantity in warehouse})$$
*   **Alert Breach**: If $\text{Available Stock} < \text{Product.minimum_stock_level}$, trigger the Alert.
*   **Notification Scope**: Send high-priority in-app notification and email digest to users with `inventory.procurement.write` permission.
*   **Automated Suggestion**: The system flags the item on the procurement dashboard with an "Auto-Reorder" recommendation, calculating the recommended order volume:
    $$\text{Reorder Volume} = \text{Target Maximum Stock} - \text{Available Stock}$$

---

## 6. eCommerce Integration Rules (Omnichannel Sync)
When the eCommerce module is enabled, the following reservation and allocation rules apply to guarantee transactional integrity:

### A. Dynamic Cart Reservations
1.  **Reservation TTL**: When an eCommerce user starts the checkout process, the system triggers a **Stock Reservation** for the items. This reserves the stock for **exactly 15 minutes**.
2.  **Stock Ledger Record**: The reservation is logged in `stock_movements` as `type = 'reserve'`, storing the `product_id`, `warehouse_id`, negative `quantity`, and `reference = 'ecom-reserve-{cart_id}'`.
3.  **Available Stock Calculation (eCom)**:
    $$\text{Net Available Stock} = \sum(\text{Quantity}) - \sum(\text{Active Reservations})$$
    *   The online storefront MUST query the **Net Available Stock** before displaying "In Stock" or permitting adding items to the checkout cart.
4.  **Auto-Release Expiry Job**: A scheduled background job runs every minute, checking for `reserve` movements older than 15 minutes without completed payments, deleting the reservation rows and releasing the stock back to the active pool.

### B. Payment Finalization & Fulfillment
1.  **Definitive Stock-Out**: On successful invoice/payment callback from eCommerce, the reserved entry (`type = 'reserve'`) is atomically converted to a standard stock-out movement (`type = 'out'`, `reference = 'ecom-order-{order_id}'`).
2.  **Abandonment Release**: If checkout is explicitly cancelled or payment times out, the `reserve` movement is instantly soft-deleted/deleted to release the allocation.

### C. Omnichannel Restocking & Quarantine
1.  **Customer Returns**: Returned products from eCommerce sales must NOT go directly back into active sellable stock.
2.  **Quarantine Warehouse**: Returns must be registered as `type = 'in'` to a **Quarantine Warehouse** or quarantined bin status.
3.  **Inspection Trigger**: Only after a formal Quality Inspection is recorded can an internal Stock Transfer be initiated to move items from Quarantine back into the main Sellable Warehouse.

---

## 7. Omnichannel Catalog Price Integration Rules

To ensure consistency across the enterprise, the Inventory SKU Catalog serves as the absolute Single Source of Truth (SSOT) for all downstream pricing and fulfillment channels:

### A. Omnichannel Selling (eCommerce & POS)

1.  **Active Status Check**: Both eCommerce storefront and Point of Sale (POS) terminals MUST only query products where `is_active = true`.
2.  **Price Fetching (eCommerce & POS)**: Sales prices displayed in retail portals are derived directly from the `products.unit_price` field in the Inventory Catalog.
3.  **Hardware Stock Checks**: Online and retail terminals must block checkouts for type `'hardware'` items if `Net Available Stock` falls below the requested purchase volume.
4.  **Software License Entitlements**: For type `'software'` products, stock counts are bypassed. Completing the sale triggers the **Software Entitlement Service** to automatically parse `product.module_ids` and activate those modules within the client tenant.

### B. Sales & CRM Pricing (Quotations & Opportunities)
1.  **B2B Opportunity Schedule**: In the CRM pipeline, B2B Opportunity schedules pull base pricing from `products.unit_price`.
2.  **Sales Quotation Auto-population**: Creating a new customer Quotation or sales Order automatically queries the Inventory catalog to populate the product's base price.
3.  **Manual Price Override Gate**: Unit prices may only be edited manually during Quotation/Order creation if the current user possesses the specific `sales.price.override` IAM permission. Manual adjustments are logged as variance flags for auditing.

### C. Procurement Price Mapping (Purchase Orders)
1.  **PO Unit Cost Sourcing**: When drafting a new Purchase Order (PO) to buy goods from suppliers, the system automatically fetches the product's default `unit_cost` or supplier-negotiated price stored in `suppliers_products` from the SCM catalog.
2.  **Dynamic Cost Override**: Purchase cost can be dynamically overridden in the PO based on vendor invoice matching, which triggers recalculation of the WAC (Weighted Average Cost) valuation on Goods Receipt.

---

## 8. Core Architectural Constraints: Single Table Product Consolidation (SSOT)

> [!IMPORTANT]
> The central Inventory Product table (`products`) is the absolute Single Source of Truth (SSOT) database structure for all products across the entire ERP system.

### A. Strict DB Schema Constraints
1.  **Zero Duplicate Tables**: No other system, service, or custom module (e.g. Sales, eCommerce, POS, CRM, Projects, Fleet, Assets, eApprovals) may define, create, or maintain separate local tables or columns to duplicate product master records.
2.  **Mandatory Foreign Keys**: Every module using products (including quotations, orders, invoices, subscriptions, CRM schedules, purchase orders, asset acquisitions, project resource logs, and fleet parts replacements) MUST link directly to `products.id` using a strict database foreign key constraint (`product_id`).
3.  **Soft Delete Cascade Safeguard**: SCM enforces standard soft-deletes (`DeletedAt`). Downstream systems must respect soft deletes and filter inactive products, while preserving historical foreign key integrity for existing orders/invoices.

### B. Single Source API Endpoints
1.  **Uniform Storefront / Admin Fetching**: All user interfaces (including Quotation item searches, eCommerce lists, POS scanner lookups, and Fleet spare-parts dropdowns) MUST consume the central Inventory product endpoints (`/api/v1/products`), completely forbidding ad-hoc or direct local queries that bypass the core module logic.
2.  **Real-time Attribute Synchronization**: Changes to descriptions, variants (`product_variants`), SKUs, branding pictures, tax structures, or selling prices made in SCM immediately apply system-wide in real-time without batch sync processes.

---

## 9. Code Numbering (Tenant-Configurable)

Purchase Order numbers read their prefix from per-tenant settings. Admins edit them under **Settings → Numbering**. Stored values include any separator (e.g. `PO-`), so the generator concatenates: `{prefix}YYYYMMDD-XXXXXX`. Changes only affect new POs.

| Entity | Setting key | Default | Format | Generator |
|---|---|---|---|---|
| Purchase Order | `numbering.po_prefix` | `PO-` | `{prefix}YYYYMMDD-{6×random}` | `ProcurementService::generatePoNumber` |

Uniqueness is enforced via the composite `(po_number, tenant_id)` index. Renaming the prefix mid-lifecycle is safe: existing PO numbers are immutable snapshots and downstream `stock_movements.reference = "PO:{po_number}"` audit trails reference the literal that existed at receive time.
