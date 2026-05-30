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

## 3. Period Closing Workflow

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
