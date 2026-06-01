# Feature Context: Accounting & General Ledger (Backend/Frontend)

Implementation phases and architecture specifications for the Accounting module, establishing the baseline double-entry journal postings, dynamic exchange rates, multi-currency processing, and period-closing safety gates.

---

## Technical Baseline

The module represents the transactional engine of truth, grounding all operational and fiscal balances in four primary tables scoped uniquely per tenant:

* **Chart of Accounts (`accounts`)**: Hierarchical tree grouping categorizing resources into Assets, Liabilities, Equity, Revenues, and Expenses.
* **Journal Entries (`journal_entries`)**: Header envelopes that hold transactional reference details and transaction dates.
* **Ledger Entries (`ledger_entries`)**: Line items specifying individual debit/credit currency values linked to a journal header.
* **Exchange Rates (`exchange_rates`)**: Conversion index providing active multipliers between functional and quote currencies.

---

## Implementation Phases

### Phase 1: Chart of Accounts & Structural Hierarchy
- [x] Schema migration & hierarchical self-FK structure on `accounts` table.
- [x] CRUD controllers and endpoints under `/api/v1/accounts` (+ `?tree=1`).
- [x] Circular parent-child loops prevention + parent-type-match invariant in `AccountService`.
- [x] Tree view balance summation page at `/accounting/accounts` (per-type KPI cards, filter chips, expandable rows).

### Phase 2: Double-Entry Ledger Posting
- [x] Journal Posting Engine (`AccountingService::postEntry`) with transaction boundaries.
- [x] Strict balance validation check ($\sum \text{Debits} == \sum \text{Credits}$) on post.
- [x] Dynamic running `balance` update mechanism on the impacted Accounts.
- [x] Frontend journal entry posting UI (`/accounting/journals`) — line builder, live balance indicator, account picker from CoA tree.
- [x] Immutability policy (`JournalEntryPolicy` + `LedgerEntryPolicy`) — update/delete blocked; `apiResource('ledger')` replaced with explicit routes.
- [x] Offsetting reversal utility (`AccountingService::reverseEntry`) — atomic DR↔CR swap with row lock; `POST /ledger/{journal}/reverse`; `reverses_journal_id` / `reversed_by_journal_id` self-FKs.
- [x] Frontend reversal UI on `/accounting/journals` — status filter chips, per-row Reverse button, confirmation modal with editable ref/memo, inline "reverses / reversed by" link badges, muted styling for reversed rows.

### Phase 3: Dynamic Multi-Currency Integration
- [x] Active `ExchangeRate` model mapping and decimal:6 representation.
- [x] Auto-standardizing ISO currency identifiers to uppercase.
- [ ] Dynamic multi-currency conversion utility (`ExchangeRateService::convert`).
- [ ] Currency conversion pipeline to automatically translate secondary transaction values to base ledger currencies during journal postings.

### Phase 4: Fiscal Closings & Trial Balances
- [-] Scanning utility to find and warn on pending draft journal items (n/a - JEs have no draft state in this codebase, they post immediately via AccountingService).
- [x] Period-closing adjusting journal posting sequence (PeriodClosingService).
- [x] Retained earnings calculation and net-income closing transfers (built into PeriodClosingService::close).
- [x] Fiscal Period status database flag to prevent post-closing ledger mutations (AccountingService::assertEntryDateNotLocked gate).
