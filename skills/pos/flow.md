# Point of Sale Workflows

This document maps the operational cashier lifecycles, real-time sync systems, and financial posting pipelines of the Point of Sale (POS) module using visual Mermaid diagrams.

---

## 1. Cashier Shift Drawer & Variance Workflow

This flow maps the lifecycle of opening a terminal shift, recording the float, performing cash skims, and supervisor variance reconciliation.

```mermaid
graph TD
    Start([Cashier Arrives at Terminal]) --> OpenCheck{Is Shift currently open?}
    OpenCheck -- Yes --> LockRegister[Register Lock: Must close previous shift first]
    OpenCheck -- No --> InputFloat[Cashier enters Opening Cash Float]
    InputFloat --> CreateShift[Create PosShift Model<br/>Status set to 'open']
    
    CreateShift --> ActiveSales[Active checkout sales enabled]
    ActiveSales --> CashFlow{Operational Cash skims<br/>or Paid-Outs?}
    CashFlow -- Yes --> LogCashFlow[Log cash drop/payout on shift<br/>Updateexpected cash running total]
    CashFlow -- No --> CloseTrigger[Cashier initiates Shift Close]
    LogCashFlow --> CloseTrigger
    
    CloseTrigger --> EnterCloseCount[Cashier counts drawer cash<br/>Submits count]
    EnterCloseCount --> CalcVariance[PosShiftService computes variance:<br/>Variance = count - expected]
    
    CalcVariance --> VarianceCheck{Is Variance = 0?}
    VarianceCheck -- Yes --> ReconcileShift[Status set to 'reconciled'<br/>Close shift & Lock register]
    VarianceCheck -- No --> FlagPending[Status set to 'variance_pending'<br/>Lock register & Dispatch manager alert]
    
    FlagPending --> SupervisorReview{Supervisor approves?}
    SupervisorReview -- Yes --> PostVariance[FMS posts balanced JE:<br/>DR Cash Over/Short Expense / CR Petty Cash]
    PostVariance --> ReconcileShift
    
    ReconcileShift --> End([Register Safe for Next Cashier])
```

---

## 2. Front-Counter Checkout & Stock-Out Workflow

This flowchart traces a standard, online barcode checkout transaction, showing the immediate WAC-cost stock-out calculation and integration.

```mermaid
sequenceDiagram
    autonumber
    actor Cashier as Cashier (Register Screen)
    participant FE as Vue 3 Client (useApi)
    participant BE as Laravel API (PosOrderService)
    participant INV as Inventory Service
    participant FMS as FMS Accounting Service

    Cashier->>FE: Scans items / Selects quick keys
    Note over FE: Cart updates locally instantly.<br/>Tax computed dynamically.
    
    Cashier->>FE: Processes multi-method payment (e.g. Cash + Card)
    FE->>BE: POST /api/v1/pos/orders/checkout { cart, payments, shift_id }
    
    activate BE
    BE->>BE: Verify active tenant connection
    BE->>BE: Verify target cashier shift is status='open'
    
    BE->>BE: Start Database Transaction (Atomic)
    
    BE->>INV: Deduct stock from register warehouse
    activate INV
    INV->>INV: Log stock movement (type='retail_sale')
    INV->>INV: Resolve line item cost at Weighted Average Cost (WAC)
    INV-->>BE: Return WAC line values
    deactivate INV
    
    BE->>FMS: Post balanced Journal Entry via postEntry()
    activate FMS
    FMS->>FMS: DR petty cash / DR card clearing<br/>CR Retail Revenue<br/>CR VAT Payable
    FMS->>FMS: DR Cost of Goods Sold (WAC cost)<br/>CR Inventory Asset (WAC cost)
    FMS-->>BE: Return Journal Entry ID
    deactivate FMS
    
    BE->>BE: Save PosOrder & PosPayment Models
    BE->>BE: Commit Database Transaction
    
    BE-->>FE: Return 200 OK { receipt: PosReceiptResource }
    deactivate BE
    
    FE->>Cashier: Print thermal receipt (80mm) & Open cash drawer
```

---

## 3. Client-Side Offline Resiliency & Sync Daemon

This diagram illustrates the resilient offline checkout capability, client-side caching in IndexedDB, and the automated reconciliation background sync daemon when connection is recovered.

```mermaid
graph TD
    StartOffline([Internet Connection Drops]) --> HeartbeatOff[Heartbeat Daemon sets network state = 'offline']
    HeartbeatOff --> RegisterRead[Register reads Product Catalog from IndexedDB local cache]
    
    RegisterRead --> ScanOffline[Cashier scans barcode and runs checkout]
    ScanOffline --> SaveLocal[Serialize Order and payments<br/>Save inside IndexedDB 'offline_orders']
    SaveLocal --> PrintOffline[Print Thermal Receipt:<br/>'OFFLINE RECEIPT - PENDING SYNC']
    
    PrintOffline --> Reconnect([Connection Restored])
    Reconnect --> HeartbeatOn[Heartbeat Daemon sets network state = 'online']
    
    HeartbeatOn --> DaemonTrigger[Trigger background Sync Daemon]
    
    subgraph Background Sync Daemon
        GetOffline[Query oldest unsynced order from IndexedDB]
        GetOffline --> SendAPI[POST /api/v1/pos/sync-offline-orders]
        
        SendAPI --> DupCheck{Order UUID already exists<br/>in database?}
        DupCheck -- Yes --> Dedup[Acknowledge sync<br/>idempotently, return 200 OK]
        DupCheck -- No --> StockCheck{Is warehouse stock<br/>sufficient?}
        
        StockCheck -- Yes --> PostSale[Save POS Order, deduct stock<br/>and post GL ledger journals]
        StockCheck -- No --> PostWarning[Save POS Order & post journals<br/>Flag movement as 'insufficient_stock_warning']
        
        Dedup --> PopQueue[Remove order from IndexedDB queue]
        PostSale --> PopQueue
        PostWarning --> PopQueue
    end
    
    PopQueue --> QueueEmpty{Is offline_orders<br/>queue empty?}
    QueueEmpty -- No --> GetOffline
    QueueEmpty -- Yes --> Complete([Offline synchronization complete])
```
