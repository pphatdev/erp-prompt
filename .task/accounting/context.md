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
- [x] CRUD controllers and endpoints under `/api/v1/fms/accounts`.
- [ ] Implement circular parent-child loops prevention validation checks on save.
- [ ] Tree view dynamic balance summation component in Nuxt UI (`/accounting/accounts`).

### Phase 2: Double-Entry Ledger Posting
- [x] Journal Posting Engine (`AccountingService::postEntry`) with transaction boundaries.
- [x] Strict balance validation check ($\sum \text{Debits} == \sum \text{Credits}$) on post.
- [x] Dynamic running `balance` update mechanism on the impacted Accounts.
- [ ] Immutability policy guarding posted items from direct API deletions or edits.

### Phase 3: Dynamic Multi-Currency Integration
- [x] Active `ExchangeRate` model mapping and decimal:6 representation.
- [x] Auto-standardizing ISO currency identifiers to uppercase.
- [ ] Dynamic multi-currency conversion utility (`ExchangeRateService::convert`).
- [ ] Currency conversion pipeline to automatically translate secondary transaction values to base ledger currencies during journal postings.

### Phase 4: Fiscal Closings & Trial Balances
- [ ] Scanning utility to find and warn on pending draft journal items.
- [ ] Period-closing adjusting journal posting sequence.
- [ ] Retained earnings calculation and net-income closing transfers.
- [ ] Fiscal Period status database flag to prevent post-closing ledger mutations.
