# Accounting & General Ledger Workflows

This document visualizes the core pipelines of the accounting module, showing how transactional data flows across models, checks, and states.

---

## 1. Double-Entry Posting Pipeline

This flow details how operational movements (such as Sales Invoices, Fixed Asset Depreciation, or payroll logs) trigger and complete general ledger entry writing.

```mermaid
graph TD
    A[Operational Event <br> e.g., Asset Depreciation] --> B[Resolve GL Accounts <br> via Tenant Settings]
    B --> C[Compose Journal Lines <br> Debits & Credits]
    C --> D{Verify Double-Entry Balance <br> Sum Debits == Sum Credits?}
    D -- No --> E[Throw Domain Exception <br> Rollback Operation]
    D -- Yes --> F[Start DB Transaction]
    F --> G[Create JournalEntry <br> status='posted']
    G --> H[Create LedgerEntry Rows <br> linked to JournalEntry]
    H --> I[Update Account Balances <br> code-specific adjustments]
    I --> J[Commit DB Transaction]
    J --> K[Log Audit Activity <br> Auditable Trait]
```

---

## 2. Multi-Currency Conversion Flow

This flow maps how transactions executed in a secondary/quote currency are translated and recorded in the tenant's primary functional ledger currency.

```mermaid
graph TD
    A[Operational Transaction <br> in Quote Currency] --> B[Standardize Currency Strings <br> e.g., KHR, USD]
    B --> C[Fetch Active ExchangeRate <br> effective_date & currency match]
    C --> D{Rate Available?}
    D -- No --> E[Raise Runtime Exception <br> Rate Not Seeded]
    D -- Yes --> F[Read decimal:6 rate multiplier]
    F --> G[Calculate Base Currency Amount <br> Quote Amount / Rate]
    G --> H[Create Balanced Entries <br> Base and Quote ledger rows]
    H --> I[Post to General Ledger]
```

---

## 3. AR Cycle — Sales to Cash

End-to-end revenue flow from a quote to the receipted cash and any post-billing adjustment.

```mermaid
graph TD
    Q[Quotation <br> /sales/quotations] -- accept --> I[Invoice <br> /sales/invoices]
    I -- confirm --> POST_AR[Post DR AR / CR Revenue]
    POST_AR --> O{Customer outcome?}
    O -- pays --> R[Receipt Payment <br> /accounting/receipts]
    R --> POST_R[Post DR Cash / CR AR]
    O -- returns / discount --> CN[Credit Note <br> /accounting/credit-notes]
    CN --> POST_CN[Post DR Sales Returns / CR AR]
    O -- under-billed --> DN[Debit Note <br> /accounting/debit-notes]
    DN --> POST_DN[Post DR AR / CR Revenue]
```

---

## 4. AP Cycle — Purchase to Payment

End-to-end disbursement flow covering both supplier and employee cash-outs.

```mermaid
graph TD
    subgraph Supplier
        PR[Request Order / PR <br> /inventory/purchase-requests] --> PO[Purchase Order <br> /inventory/purchase-orders]
        PO -- receipt --> GRN[Goods Receipt <br> DR Inventory / CR GR-IR]
        GRN --> B[Bill <br> /accounting/bills <br> DR Expense or GR-IR / CR AP]
        B --> PB[Pay Bill <br> /accounting/bills/pay <br> DR AP / CR Cash]
        B -- under/over-billed --> SDN[Supplier Debit Note <br> DR AP / CR Expense]
    end

    subgraph Employee
        CA[Cash Advance <br> /accounting/cash-advances <br> DR Emp Advances / CR Cash]
        CA --> AS[Advance Settlement <br> DR Expense / CR Emp Advances <br> + DR Cash on unused]

        OOP[Out-of-Pocket Spend] --> RM[Reimbursement <br> /accounting/reimbursements <br> DR Expense / CR Cash]
    end

    subgraph Direct
        EX[Expense <br> /accounting/expenses <br> DR Expense / CR Cash or Credit Card]
    end
```

---

## 5. Non-Current Asset Lifecycle

```mermaid
graph TD
    CPO[Capitalizable PO <br> /inventory/purchase-orders] --> REG[Register Asset <br> /assets <br> DR Asset / CR AP or Cash]
    REG -- monthly close --> DEP[Depreciation <br> /assets/depreciation <br> DR Depreciation Exp / CR Accum Depr]
    REG -- retire --> DIS[Asset Disposal <br> /assets/disposal]
    DIS --> POST_DIS[Post DR Cash + DR Accum Depr <br> CR Asset <br> ± DR/CR Gain or Loss]
```

---

## 6. Period Closing Workflow

This flow represents the end-of-period closing sequence that freezes historical accounts, rolls balances forward, and prepares the tenant's ledger for a new fiscal period.

```mermaid
graph TD
    A[Initiate Period Closing] --> B[Scan for Draft Journal Entries]
    B --> C{Any Drafts Found?}
    C -- Yes --> D[Error: Post or Delete <br> Draft Journals First]
    C -- No --> E[Verify COA Balance <br> Trial Balance Check]
    E --> F[Record Adjusting/Closing Entries <br> e.g., Depreciation, Accruals]
    F --> G[Summarize Revenue & Expenses <br> Transfer to Retained Earnings]
    G --> H[Roll Balances Forward <br> Establish opening balances]
    H --> I[Mark Period as Locked <br> block future ledger writes]
    I --> J[Generate Final Financial Statements <br> Balance Sheet / P&L]
```
