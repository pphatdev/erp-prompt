# Feature Context: Financial Management (FMS) (Backend)

Implementation phases for the FMS module, focusing on double-entry accounting and multi-tenant financial isolation.

## Implementation Phases (Backend Only)

### Phase 1: Accounting Foundation & Ledger
- [x] Create migrations for `accounts` (Chart of Accounts), `journal_entries`, and `ledger_entries`.
- [x] Implement `Account`, `JournalEntry`, and `LedgerEntry` models with `BelongsToTenant` and `Auditable`.
- [x] Define the hierarchical structure for the Chart of Accounts.

### Phase 2: Double-Entry Accounting Engine
- [x] Implement `AccountingService` to handle balanced Journal Entries (Debit = Credit).
- [x] Implement immutability rules (Entries can only be reversed, not deleted).
- [x] Create `AccountController` for managing the Chart of Accounts.
- [x] Implement `AccountResource` and `LedgerResource`.

### Phase 3: AP/AR & Bank Management
- [ ] Implement logic for Accounts Payable (Vendor Bills) and Accounts Receivable (Customer Invoices).
- [ ] Create `TransactionService` for bank reconciliation placeholders.
- [ ] Define `fms.accounts.*` and `fms.ledger.*` permission policies.

### Phase 4: Financial Reporting Aggregates
- [ ] Implement service methods to generate Balance Sheet and P&L aggregates.
- [ ] Create `ReportingController` for financial statement retrieval.
- [ ] Ensure all financial actions generate appropriate `audit_logs`.

### Phase 5: QA & Financial Integrity Testing
- [x] P0 Balanced Entry tests (Assert that total Debit = total Credit).
- [x] P0 Immutability tests (Assert that posted entries cannot be deleted).
- [x] P0 Tenancy Isolation for financial data.

