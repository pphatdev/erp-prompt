# Feature: Point of Sale (POS)

## Overview

The **Point of Sale (POS)** module is the high-velocity, transaction-focused retail interface for the multi-tenant ERP system. Built for physical front-counters, brick-and-mortar storefronts, and pop-up registers, the POS system facilitates fast customer checkouts, handles multiple payment types (cash, credit, mobile, store credit), manages cash drawer cashier shifts (opening float, cash-in/out, closing counts, variances), and provides resilient offline capabilities to handle spotty internet connections.

Architecturally, the POS module behaves as a **real-time transactional bridge** that instantly orchestrates updates across three major domains:
1. **Inventory**: Automatically decrements localized warehouse stock levels and records stock movements at the moment of sale.
2. **FMS (Finance)**: Generates client receipts, logs retail invoices, processes bank/cash reconciliation ledger entries, and writes double-entry general ledger journal impacts.
3. **CRM (Customers)**: Integrates customer profiles, increments loyalty points, and allows the redemption of store credits or gift vouchers.

---

## Module Taxonomy

The Point of Sale menu surface (top-level sidebar group `pos`) divides its operational, configuration, and audit capabilities into six primary families:

### 1. Terminal & Shift Management
The security and cash containment controls.
- **Register Registry** — Configuration for physical POS registers, linking each terminal to a specific warehouse stock source and active bank/petty-cash ledger accounts.
- **Cashier Shift Logs** — Tracking cashier sessions. Casher must log an *Opening Float* to begin, record operational *Cash Drops* (skims) or *Paid-Outs*, and execute a *Closing Count* at shift completion.
- **Manager Audit Board** — Real-time tracking of open shifts across registers, displaying active cash balances, and logging cashier cash variances (overs/shorts).

### 2. Retail Cashier Interface (Register)
The high-speed checkout workspace.
- **Interactive Register Screen** — Responsive grid containing catalog categories and "Quick Pick" items, optimized for touchscreens and tablet viewports.
- **Barcode Parsing Engine** — Fast input listener capturing standard UPC/EAN scans, automatically incrementing cart items without manual search.
- **Concurrent Carts Manager** — Affordance allowing cashiers to park/suspend an active cart (e.g., when a customer needs to grab another item) and serve another customer without losing cart state.

### 3. Customer Engagement & Loyalty (CRM Link)
The relationship tracking workspace.
- **Customer Lookup Overlay** — Rapid search by phone number, email, or loyalty ID to link retail sales to specific CRM profiles.
- **Loyalty & Store Credit Resolver** — Interface displaying the linked customer's accrued loyalty points, active promotions, and available store credit balance.
- **New Profile Creator** — Minimal-input dialog to quickly register a new walk-in customer directly from the register screen.

### 4. Checkout & Split Payments
The financial settlement engine.
- **Multi-Method Tender Grid** — Split-payment interface enabling customers to pay using combinations of cash, external credit/debit card, store credit, or bank mobile transfers.
- **Change Calculator** — Cash change display to prevent mathematical cashier errors.
- **Receipt Template Engine** — Custom receipt formatting, driving thermal printers (80mm/58mm) and automatically emailing PDF copies.

### 5. Offline Resiliency Engine (Client-Side)
The business continuity workspace.
- **Catalog Synchronization Service** — Frontend background service caching product catalogs, prices, and barcodes locally using IndexedDB.
- **Offline Transaction Queue** — Mechanism wrapping local sales in offline cart queues when internet connectivity drops.
- **Heartbeat & Reconciliation Daemon** — Automatic synchronization daemon detecting connection recovery, pushing queued offline sales to the backend, and resolving transaction collisions.

### 6. Analytics & Retail Insights
The performance reporting dashboard.
- **Daily Sales Reconciler** — Aggregated dashboards summarizing gross sales, tax collected, cash collected, and inventory value moved.
- **Product Velocity Analyzer** — Sales velocity tracker showing best-selling retail categories and individual products.
- **Hourly Sales Distribution** — Trend analyzer tracking peak retail traffic hours to optimize store staffing.

---

## Cross-Module Integration Contract

The POS module sits at the intersection of several enterprise modules, interacting via clean service APIs:

```
                         ┌─────────────────────────────┐
                         │   INVENTORY MANAGEMENT      │
                         │   - Warehouse Stock Alloc   │
                         │   - WAC Cost Matching       │
                         │   - Stock Movement Ledger   │
                         └──────────────┬──────────────┘
                                        │ Decrements Stock
                                        ▼
┌──────────────────────────────────────────────────────────────┐
│                    POINT OF SALE (POS)                       │
│   - Cashier Shift Float       - Checkout Cart Sales          │
│   - Multi-Method Payments     - Offline IndexedDB Syncer     │
└───────────────────────────────┬──────────────────────────────┘
                                │
                                ├──────────────────────────────┐
                                │ Logs Payments & Journals     │ Logs Loyalty & Credits
                                ▼                              ▼
                 ┌─────────────────────────────┐┌──────────────┴──────────────┐
                 │   FINANCIALS & LEDGER (FMS) ││       CRM (Customers)       │
                 │   - Balanced GL Journals    ││   - Customer Profile Link   │
                 │   - Tax Compliance Rates    ││   - Loyalty Points Accrual  │
                 │   - Bank/Cash Ledger Sync   ││   - Store Credit Balance    │
                 └─────────────────────────────┘└─────────────────────────────┘
```

1. **Inventory Module (P0 Stock-Out)**:
   - Registers are bound to specific `warehouses`. All product stock-on-hand validations read directly from that warehouse's inventory record.
   - Upon sale, the POS dispatches stock out events via `InventoryService::recordMovement()`. Stock is deducted, and inventory cost balances are calculated using Weighted Average Costing (WAC).
   - If stock falls below safety levels, the POS triggers the Inventory system's alert system to queue automatic purchase order suggestions.

2. **FMS Module (P0 Ledger Postings)**:
   - Cashier shift opening float and closing registers post directly to the ledger.
   - Successful checkout transactions call the `AccountingService::postEntry()` pipeline inside an atomic database transaction.
   - The transaction posts balanced GL impact lines:
     * `DR Cash Account (Register specific GL)` OR `DR Card Clearing Account` / `CR Retail Sales Revenue`
     * `DR Cost of Goods Sold` / `CR Inventory Asset Account` (based on WAC costs resolved from Inventory).
     * `DR Sales Revenue / CR Tax Payable` (calculating local VAT/Sales Tax rates defined in settings).

3. **CRM Module (P1 Loyalty & Wallet)**:
   - When a sale is linked to a customer, CRM loyalty balance records increment points based on the configured retail ratio (e.g., 1 point per $10 spent).
   - Redemptions of store credit invoke CRM balance debits, validating that the customer's wallet balance does not go negative.
