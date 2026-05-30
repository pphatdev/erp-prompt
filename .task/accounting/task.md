# Task Checklist: Accounting & General Ledger

> See [`skills/accounting/skill.md`](../../skills/accounting/skill.md) for the canonical Accounting scope. The module manages Chart of Accounts, General Ledger transaction postings, exchange rates, and fiscal periods.

---

## Shipped & Completed

- [x] **Chart of Accounts Foundation**: Multi-tenant database migrations for hierarchical `accounts` table.
- [x] **Chart of Accounts Model**: `Account` model with UUID key generation, `parent` & `children` relations, and `BelongsToTenant` scoping traits.
- [x] **Double-Entry Ledger Migration**: Scaffolding for `journal_entries` and `ledger_entries` tables.
- [x] **General Ledger Core Models**: `JournalEntry` and `LedgerEntry` models utilizing `BelongsToTenant` and `Auditable` traits.
- [x] **Balanced Posting Service**: Core ledger posting operations under `AccountingService::postEntry()` enforcing balanced debits/credits check and atomically adjusting account running balances.
- [x] **General Ledger API Controllers**: Lightweight endpoints for fetching and posting entries.
- [x] **Exchange Rates Engine**: Database schema, model with case uppercase standardization, `ExchangeRateService` lookup mechanisms, and standard CRUD endpoints.
- [x] **Exchange Rates UI**: Dedicated PrimeVue management board for managing quotes, converting balances, and configuring currency conversions.

---

## Phase 1 — Chart of Accounts UI & Loops Guard (Planned)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § 2.B*

- [ ] **Circular Loop Prevention**: Add model-level validation checking that prevents circular hierarchies (an account parent can never be a child of itself or its descendants).
- [ ] **Delete Protection**: Prevent deleting any account that has posted ledger entries or active child sub-accounts.
- [ ] **Tree Summation Component**: Implement hierarchical balance summation queries, allowing parent accounts to aggregate balances of children.
- [ ] **Chart of Accounts UI Grid**: A tree-table layout page under `pages/accounting/accounts.vue` displaying the active tenant's ledger hierarchy with dynamic running balances.

---

## Phase 2 — Double-Entry API Guard & Reversals (Planned)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § 2.A*

- [ ] **Immutability Enforcement Policy**: Wire up policy guards preventing any database deletion or mutation on `posted` journal and ledger items.
- [ ] **Offsetting Reversal Utility**: Implement `AccountingService::reverseEntry(JournalEntry $journal)` that automatically posts a balanced offsetting journal entry referencing the original ID for correction tracking.
- [ ] **Journal Posting Form Validation**: Add frontend validation checks preventing posting inputs if the debit total does not equal the credit total.
- [ ] **Ledger Entry UI Screen**: Dynamic layout under `pages/accounting/journals.vue` displaying posted journals and incorporating a balanced form builder for manual adjustments.

---

## Phase 3 — Currency Conversion Pipeline (Planned)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § 2.C*

- [ ] **Base Currency Fallback Config**: Establish standard tenant setting for core ledger currency (default: `USD`).
- [ ] **Automated Conversion Utility**: Enhance `AccountingService` to dynamically lookup quote-to-base exchange rates and automatically post translated functional currency rows alongside transaction currency values.
- [ ] **Rounding Variance Buffer**: Handle rounding adjustments to prevent unbalanced entries due to floating decimal translations.

---

## Phase 4 — Period-End Closings & Locks (Planned)
*Reference: [`skills/accounting/flow.md`](../../skills/accounting/flow.md) § 3*

- [ ] **Fiscal Period Status Flag**: Add `status` column to periods metadata indicating if the timeframe is `open` or `locked`.
- [ ] **Write Block Middleware**: Add middleware blocking all general ledger posting routines if `entry_date` falls within a locked fiscal period.
- [ ] **Closing Balance Rollover Service**: Automate calculations summarizing net income and rolling balances forward to establish next-period opening lines.
