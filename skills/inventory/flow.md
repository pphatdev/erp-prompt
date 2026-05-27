# Feature Flows: Inventory Management & SCM

## 1. Unified Product Catalog Flow (Single Source of Truth)
This flowchart shows how the Inventory Catalog serves as the absolute Single Source of Truth (SSOT) across all sales, front-end retail, CRM, and procurement channels.

```mermaid
graph TD
    subgraph SSOT [Single Source of Truth Catalog]
        Inventory[Inventory Core SKU Catalog]
        InvHardware[Hardware: Physical Stock in WH Bins]
        InvSoftware[Software: Digital Module Licenses]
        Inventory --> InvHardware
        Inventory --> InvSoftware
    end

    subgraph Channels [Retail & Front-End Channels]
        ECom[eCommerce Portal]
        POS[Point of Sale Terminal]
    end

    subgraph B2B [Sales & CRM Pipelines]
        CRM[CRM Pipeline / Opportunities]
        Sales[Sales Quotation Engine]
    end

    subgraph Procurement [Procurement & Supply Chain]
        P2P[Purchase Orders / PO]
    end

    %% eCommerce & POS Relationships
    Inventory -->|Query SKU, Variant Attributes & Catalog Price| ECom
    Inventory -->|Direct SKU Scan, Price Lookup & Stock Check| POS
    ECom -->|Complete Order: Deduct Stock| InvHardware
    POS -->|Checkout Completed: Deduct Stock| InvHardware
    ECom -->|Active Software Sale: Auto-Entitle Tenant Module| InvSoftware

    %% CRM & Sales Relationships
    Inventory -->|Opportunity B2B Product Schedule Pricing| CRM
    Inventory -->|Quotation Builder: Auto-populate Unit Price| Sales
    Sales -->|Won Quotation: Auto-generate Sales Order| Sales
    Sales -->|Fulfill Order: Decrement Stock| InvHardware

    %% Procurement Relationships
    Inventory -->|Supplier PO: Fetch Negotiated Cost / Valuation Price| P2P
    P2P -->|Validate Goods Receipt Note / GRN| InvHardware

    style SSOT fill:#d9edf7,stroke:#31708f,stroke-width:2px
    style Channels fill:#dff0d8,stroke:#3c763d,stroke-width:2px
    style B2B fill:#fcf8e3,stroke:#8a6d3b,stroke-width:2px
    style Procurement fill:#f2dede,stroke:#a94442,stroke-width:2px
```

---

## 2. Procure-to-Pay (P2P) Procurement Workflow
This flow maps the lifecycle of purchasing inventory from suppliers, moving from initial request through approval, shipment, quality inspection, stock-in, and final invoice matching.

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Department Requestor
    actor Mgr as Approving Manager
    participant Procure as Procurement Service
    actor Supplier as External Vendor
    participant Warehouse as Stock & Warehouse
    participant Finance as FMS (Ledger)

    Staff->>Procure: Create Purchase Requisition (PR)
    Procure->>Mgr: Route to eApprovals Queue
    Mgr->>Procure: Approve Requisition
    Procure->>Procure: Auto-generate Purchase Order (PO)
    Procure->>Supplier: Dispatch PO (PDF/Email)
    Supplier->>Warehouse: Ship Goods & Delivery Note
    Warehouse->>Warehouse: Goods Receipt Note (GRN) & Quality Check
    alt Quality Pass
        Warehouse->>Warehouse: Stock-In (Increment quantity)
        Warehouse->>Finance: Trigger GL Entry (Debit Inventory, Credit AP Accrual)
        Procure->>Procure: Mark PO as "Received" / "Closed"
    else Quality Fail
        Warehouse->>Supplier: Generate Return/Rejection Log
        Warehouse->>Warehouse: Store in Quarantine/Inspect Bin
    end
    Supplier->>Finance: Send Invoice
    Finance->>Finance: 3-Way Match (PO + GRN + Invoice)
    Finance->>Finance: Release Payment (Debit AP Accrual, Credit Cash)
```

---

## 3. Atomic Inter-Warehouse Stock Transfer Flow
Stock transfers between physical warehouses must be transaction-safe, locking the records and enforcing stock availability to prevent concurrent double-booking of stock.

```mermaid
graph TD
    A[Initiate Stock Transfer Request] --> B[Validate Permissions & Input]
    B --> C{Verify Available Stock at Origin}
    C -- Insufficient --> D[Throw InsufficientStockException]
    C -- Available --> E[Open DB Transaction]
    
    E --> F[Lock Product Record: Product::lockForUpdate]
    F --> G[Verify Stock Level Again inside Lock]
    
    G -- Insufficient --> H[Rollback Transaction & Abort]
    G -- Available --> I[Record transfer_out Movement at Origin]
    
    I --> J[Record transfer_in Movement at Destination]
    J --> K[Update Unit Cost records if applicable]
    
    K --> L[Commit DB Transaction]
    L --> M[Trigger Low-Stock Alert Engine check on Origin]
    M --> N[Transfer Completed Successfully]
    
    style E fill:#f9f,stroke:#333,stroke-width:2px
    style L fill:#9f9,stroke:#333,stroke-width:2px
    style H fill:#f99,stroke:#333,stroke-width:1px
    style D fill:#f99,stroke:#333,stroke-width:1px
```

---

## 4. Stock Take & Cycle Count Audit Flow
Periodic auditing of physical inventory against the system database ledger to perform reconciliations and log audit trail entries.

```mermaid
graph TD
    A[Schedule Cycle Count / Stock-Take] --> B[Lock Warehouse / Bin for Counts]
    B --> C[Generate Count Sheet for Staff]
    C --> D[Staff Records Physical Counts]
    D --> E[Enter Counts into Reconciliation Screen]
    
    E --> F{System Qty == Physical Qty?}
    F -- Yes --> G[Unlock Warehouse: No Action Required]
    F -- No --> H[Calculate Variance: Physical - System]
    
    H --> I{Variance Exceeds Threshold?}
    I -- Yes --> J[Route Variance to Manager for Approval]
    J -- Rejected --> K[Initiate Recount/Audit]
    J -- Approved --> L[Open DB Transaction]
    I -- No --> L
    
    L --> M[Record stock_adjustment Movement with Variance Qty]
    M --> N[Log Audit Log with Reason Code & Manager Approved Actor]
    N --> O[FMS Sync: Debit/Credit Shrinkage Expense Account]
    O --> P[Commit Transaction & Unlock Warehouse]
    
    style L fill:#f9f,stroke:#333,stroke-width:2px
    style P fill:#9f9,stroke:#333,stroke-width:2px
```

---

## 5. eCommerce Cart Reservation & Fulfillment Flow
Tracks online shopping stock reservation during checkout, TTL monitoring, automatic releases, and final conversion to sales stock-out.

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as eCommerce Customer
    participant Cart as eCommerce Cart / Checkout
    participant Stock as SCM Stock Engine
    participant Cron as SCM Background Daemon
    participant Payment as Payment Gateway

    Buyer->>Cart: Propose Checkout (Items X, Y)
    Cart->>Stock: Request Stock Reservation (X, Y)
    Note over Stock: Lock Product row & verify Net Available Stock
    alt Stock Available
        Stock->>Stock: Insert reserve Movements (TTL: 15m)
        Stock-->>Cart: Reservation Approved (Reserve ID)
        Cart->>Buyer: Render Payment Gate View
    else Stock Out
        Stock-->>Cart: Refuse Reservation
        Cart-->>Buyer: Show "Stock Out / Insufficient Stock"
    end

    alt Complete Checkout (Payment Successful)
        Buyer->>Payment: Pay Invoice
        Payment->>Cart: Send Success Callback
        Cart->>Stock: Commit Reservation (Reserve ID)
        Stock->>Stock: Update movement type from 'reserve' to 'out' (Reference: Order ID)
        Stock-->>Buyer: Order Confirmed
    else Abandon / Timeout (15 minutes elapsed)
        Cron->>Stock: Scan expired reservations (>15m)
        Stock->>Stock: Delete 'reserve' Movements
        Stock-->>Cart: Release stock allocation to active pool
    end
```
