# Task Checklist: Accounting & General Ledger

> See [`skills/accounting/skill.md`](../../skills/accounting/skill.md) for the canonical Accounting scope. Accounting is the **accountant's lens** across the whole ERP — it owns the double-entry engine and cross-links operational modules (Sales, Inventory, HRM, Assets) for everything else.

Legend: ✅ shipped · ◐ partial · 🔗 cross-link (lives in another module) · ⬜ planned

---

## A. Core (owned by Accounting / FMS namespace)

### Chart of Accounts (Shipped 2026-06-01)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § A, § 2.B*

- [x] Hierarchical `accounts` migration + `Account` model with `BelongsToTenant` / `Auditable` / `SoftDeletes`.
- [x] `AccountService` — create / update / archive / tree (rolled-up `aggregatedBalance`).
- [x] **Circular Loop Prevention** — parent cannot equal self or any descendant; parent-type must match sub-account type.
- [x] **Delete Protection** — archive refuses if children or any `ledger_entries` exist.
- [x] **Tree Summation** — `AccountController@index?tree=1`.
- [x] **UI** — `pages/accounting/accounts.vue` with per-type KPIs, type-filter chips, create/edit/archive modals.
- [x] `AccountPolicy` registered; `fms.accounts.{read,write,delete}` seeded.

### General Ledger / Journals (Shipped 2026-06-01)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § A, § 2.A*

- [x] `journal_entries` + `ledger_entries` migrations; `JournalEntry` + `LedgerEntry` models with `Auditable`.
- [x] `AccountingService::postEntry()` — balanced posting wrapped in `DB::transaction`, atomic account-balance update.
- [x] **Immutability Policies** — `JournalEntryPolicy` + `LedgerEntryPolicy` block update/delete; explicit GET/POST routes replace `apiResource('ledger')`.
- [x] **Reversal Utility** — `AccountingService::reverseEntry($journal, ?$ref, ?$memo)` with row lock, DR↔CR swap, `reverses_journal_id` / `reversed_by_journal_id` self-FKs (migration 000077). Endpoint `POST /api/v1/ledger/{journal}/reverse`.
- [x] **Journals UI** — `pages/accounting/journals.vue` — paginated table + posting modal with live DR=CR validation + per-row Reverse action + inline link badges + status-filter chips.

### Exchange Rates (Shipped)
- [x] Schema + `ExchangeRate` model with uppercase normalization + `decimal:6` rate cast.
- [x] `ExchangeRateService` with `latest()` / `convert()` (inverse-pair fallback).
- [x] PrimeVue management UI at `/finance/exchange-rates` (will be re-grouped under Accounting in a future sidebar pass).

### Phase 3 — Currency Conversion Pipeline (Planned)
*Reference: [`skills/accounting/rules.md`](../../skills/accounting/rules.md) § 2.C*

- [ ] **Base Currency Fallback Config** — tenant setting `accounting.base_currency` (default `USD`).
- [ ] **Automated Conversion Utility** — `AccountingService::postEntry` accepts `currency` + `fx_rate`, writes `functional_*` cols on `ledger_entries`.
- [ ] **Rounding Variance Buffer** — auto-balancing rounding line when translation introduces sub-cent variance.

### Phase 4 - Period-End Closings & Locks (Shipped 2026-06-01)
*Reference: [`skills/accounting/flow.md`](../../skills/accounting/flow.md) § 6*

- [x] **Fiscal Period table** - `fiscal_periods` migration (000091) with status (open/locked), date range, locked_at/locked_by, retained_earnings_account_id (set at close), closing_journal_entry_id (set null on delete), notes. Composite index on (tenant_id, status, start_date, end_date) for the write-block lookup.
- [x] **Write-Block** - implemented directly inside `AccountingService::postEntry` (not a middleware, since the gate must run in transaction with the JE create). `assertEntryDateNotLocked($entryDate)` runs before JournalEntry::create, checks fiscal_periods table for any locked period covering the entry_date, throws DomainException with the offending period number. Every existing posting service (Bills, Pay Bill, Reimbursement, Receipt, Credit Note, Debit Note, Expense, Cash Advance, Settlement, etc.) automatically inherits the block via the AccountingService dependency. The closing service bypasses by design: it posts the closing JE BEFORE flipping the period to locked.
- [x] **Closing Balance Rollover Service** - `PeriodClosingService` actions: `create`/`update`/`delete` (open periods only, overlap check on create), `preview($period, $reAccountId)` returning the planned closing entry without posting (powers UI dry-run), `close($period, $data)` which groups posted ledger_entries by account in the period (excluding reversed JEs), splits Revenue and Expense accounts, builds the balanced rollover JE (DR each revenue = period credit-debit movement, CR each expense = period debit-credit movement, CR/DR Retained Earnings for the net), posts via AccountingService::postEntry, then flips period.status=locked + records closing_journal_entry_id + locked_at + locked_by. `reopen($period)` clears the lock but intentionally leaves the closing JE in the ledger (reversing it is a separate AccountingService::reverseEntry call).
- [x] `FiscalPeriod` model with status constants, relations retainedEarningsAccount/closingJournalEntry, helpers `isOpen`/`isLocked`/`isClosable`/`isReopenable`/`contains(date)`. `FiscalPeriodPolicy` with separate `close` and `reopen` perms gating the irreversible transitions.
- [x] `FiscalPeriodController` + `FiscalPeriodResource`. Routes: `apiResource('fiscal-periods')` (drafts mutable) plus `GET /{period}/closing-preview?retained_earnings_account_id={id}`, `POST /{period}/close`, `POST /{period}/reopen`. Index filters: `?status`, `?search`.
- [x] Perms `fms.fiscal_periods.{read,write,close,reopen}` seeded. close and reopen are separate perms so they can be assigned to a "closer" role independent of edit. Module slug `accounting-fiscal-periods` seeded under `accounting` (sort_order 8, `ti-calendar-check`).
- [x] UI: `pages/accounting/fiscal-periods/index.vue` lists periods with KPI strip (count / open / locked), status chips, click-through. New-period modal with overlap-friendly validation. `pages/accounting/fiscal-periods/[id].vue` drill-in: when open, shows Retained Earnings picker (filtered to equity accounts) and a live closing preview with 4-card summary (Revenue Total / Expense Total / Net (profit/loss) / Retained Earnings DR or CR) plus side-by-side tables of DR Revenue accounts and CR Expense accounts. Close button posts the rollover JE and locks. When locked, the drill-in switches to a confirmation card showing the closing JE reference and locked-at date, with Reopen button gated by perm.
- [x] Sidebar: "Fiscal Periods" entry under Accounting (`ti-calendar-check`); breadcrumb `fiscal-periods -> 'Fiscal Periods'`.

---

## B. Bank & Budget

### Bank (Shipped 2026-06-01)
- [x] `bank_accounts` table — migration `000079`. UUID PK + nullable `account_id` FK to `accounts.id` (service-validated to type=`asset`). Columns: name, bank_name, branch, account_number, account_holder, swift, iban, currency (CHAR(3) default USD), opening_balance, last_reconciled_at, last_reconciled_balance, notes, is_active, is_default, tenant_id, softDeletes.
- [x] `BankAccount` model — `BelongsToTenant` + `Auditable` + `SoftDeletes`; `glAccount()` BelongsTo; `bookBalance()` returns the linked GL `accounts.balance` (single source of truth, never cached).
- [x] `BankAccountService` — create/update/archive. Enforces `account_id` is asset type; auto-demotes other defaults when promoting one (single default invariant); archive refuses while `is_default=true`.
- [x] `BankAccountPolicy` registered in `TenantServiceProvider`; `fms.bank_accounts.{read,write,delete}` seeded in `TenantDatabaseSeeder`.
- [x] `BankAccountController` + `BankAccountResource` (camelCase, `glAccount` snapshot, `bookBalance` live). `apiResource('bank-accounts')` wired with `?search` / `?currency` / `?is_active` / `?default_only` filters.
- [x] Module slug `accounting-bank` seeded.
- [x] UI: `pages/accounting/bank.vue` — glass-card grid with default chip, masked account number, currency badge, linked-GL row, book balance, last-reconciled freshness; KPI strip (total / book balance / currencies / needs-recon &gt; 30d); filters (currency, active toggle); create/edit modal with GL asset-account picker (lazy-loaded via `finance.accounts.tree()`).
- [x] Sidebar: "Bank" entry under Accounting group (`ti-building-bank`), breadcrumb map updated.
### Bank Reconciliation (Shipped 2026-06-01)
- [x] `bank_recon_sessions` + `bank_recon_statement_lines` migrations (000089). Header FKs: `bank_account_id` -> bank_accounts (restrict). Statement-line FKs: `session_id` -> bank_recon_sessions (cascade), `matched_ledger_entry_id` -> ledger_entries (nullable, set null on delete). Unique `(session_number, tenant_id)`. Statuses: `open` / `closed` (immutable once closed; reopen gated separately).
- [x] `BankReconSession` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; relations `bankAccount`, `statementLines`; helpers `isOpen()`/`isClosed()`, `statementLinesTotal()`, `unmatchedLinesCount()`, `balanceMatches()` (opening + sum(lines) == statement_ending_balance within 0.001 tolerance), `isClosable()`. `BankReconStatementLine` with `session()` + `matchedLedgerEntry()` relations; `isMatched()`, `isDeposit()`, `isWithdrawal()` helpers. Signed amount: positive = deposit (DR cash on books), negative = withdrawal (CR cash).
- [x] `BankReconService` actions: `open($data)` (validates bank GL link, dates, refuses second open session per bank, seeds opening_balance from `bank_account.last_reconciled_balance`), `addStatementLine($session, $data)` (open-only), `removeStatementLine($line)` (unmatched-only), `match($line, $ledgerEntryId)` (validates ledger entry on session's bank GL, not double-matched across sessions, sign agreement, magnitude agreement), `unmatch($line)`, `close($session)` (refused unless all lines matched AND balance check passes; updates `bank_account.last_reconciled_at`/`last_reconciled_balance` so the next session seeds correctly), `reopen($session)` (gated by separate perm), `periodLedgerEntriesQuery($session)` (helper that returns the bank's GL ledger entries within the period filtering reversed JEs, used by the UI matcher).
- [x] `BankReconSessionPolicy` registered with new `modify`/`close`/`reopen` abilities. `update`/`delete` blocked.
- [x] `BankReconController` + `BankReconSessionResource` + `BankReconStatementLineResource` (camelCase, exposes derived `expectedEndingBalance`, `balanceMatches`, `unmatchedLinesCount`, `isClosable`, plus direction-tagged lines). Routes (no apiResource): `GET/POST /bank-reconciliations`, `GET show`, `POST close/reopen`, `POST {session}/statement-lines`, `DELETE statement-line`, `POST statement-line match/unmatch`, `GET {session}/period-ledger-entries` (matcher helper returning each entry tagged with `direction`, `amountAbs`, and `matchedInSession` so the UI can show "matched in this session", "matched in another", or offer the match action).
- [x] Perms seeded: `fms.bank_recon.{read,write,reopen}`. `reopen` is a separate perm so closing is a real lock by default. Module slug `accounting-bank-reconciliation` seeded as sibling of `accounting-bank` (sort_order 6 under `accounting`, `ti-checks`). Future IA pass may collapse them under a Bank subgroup; slug is stable.
- [x] UI: `pages/accounting/bank-reconciliation/index.vue` lists sessions with KPI strip (count / open / closed / latest-close-date), status chips, click-through to drill-in. New-session modal with bank picker (auto-fills opening_balance from `lastReconciledBalance`).
- [x] UI: `pages/accounting/bank-reconciliation/[id].vue` drill-in with 5-card balance summary (Opening / Lines Total / Expected / Statement / Balance Check), two side-by-side tables: Statement Lines (click to select, shows match status with unmatch/delete actions) and Period Ledger Entries (each tagged in-this / other / actionable, with match button enabled only when a statement line is selected). Add Statement Line modal accepts signed amount with clear deposit/withdrawal hint. Close button enabled only when `isClosable` (all matched + balance check passes). Reopen button visible only with `fms.bank_recon.reopen`.
- [x] Sidebar: "Bank Reconciliation" entry under Accounting (`ti-checks`); breadcrumb `bank-reconciliation -> 'Bank Reconciliation'`.

### Budget (Shipped 2026-06-01)
- [x] `budgets` + `budget_lines` migrations (000090). Header: budget_number, name, start_date, end_date, status (draft/active/archived), notes. Unique (budget_number, tenant_id). Lines FKs: `budget_id` -> budgets (cascade), `account_id` -> accounts (restrict). Unique (budget_id, account_id) so each account appears once per budget. Single signed `expected_amount` per line (no debit/credit pair — the service uses account natural balance to interpret).
- [x] `Budget` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; status constants, relations `lines`; helpers `isDraft`, `isActive`, `isArchived`, `isEditable` (drafts only), `isActivatable` (draft + at least one line), `isArchivable` (active only), `expectedTotal()`. `BudgetLine` with `budget()` + `account()` relations.
- [x] `BudgetService` actions: `create`/`update` (drafts only), `delete` (drafts only), `addLine`/`updateLine`/`removeLine` (drafts only, uniqueness enforced), `activate` (draft + has lines), `archive` (active only). `computeVariance(budget)` groups posted `ledger_entries` by account within the period (excludes reversed JEs), interprets natural balance per account type (asset/expense use debit-credit, revenue/liability/equity use credit-debit), returns per-line `{ expected, actual, variance, variancePct, bucket }`. Bucket thresholds: green |%| < 5, yellow < 15, red otherwise. Zero-expected lines are red unless actual is also zero.
- [x] `BudgetPolicy` registered with `viewAny`/`view`/`create`/`update`/`delete` + `activate`/`archive` abilities. Update/delete blocked on non-drafts.
- [x] `BudgetController` + `BudgetResource` + `BudgetLineResource` (camelCase). Routes use `apiResource('budgets')` since drafts allow CRUD, plus explicit `POST {budget}/activate`, `POST {budget}/archive`, `GET {budget}/variance` (returns budget with variance attached), `POST {budget}/lines`, `PATCH /budget-lines/{line}`, `DELETE /budget-lines/{line}`. Index filters: `?status`, `?from`, `?to`, `?search` (matches budget_number or name).
- [x] Perms `fms.budgets.{read,write,delete}` seeded. Module slug `accounting-budgets` seeded as sibling of Bank Reconciliation (sort_order 7 under `accounting`, `ti-target`).
- [x] UI: `pages/accounting/budgets/index.vue` lists budgets with KPI strip (count / active / draft / archived), status chips, click-through to drill-in. New-budget modal collects number, name, dates. Routes to drill-in on create.
- [x] UI: `pages/accounting/budgets/[id].vue` drill-in with 4-card summary (Expected Total / Actual Total / Variance / Health: green/yellow/red counts). Variance table per account row showing traffic-light dot, account code+name+type, expected (inline editable on drafts, saved on blur or enter), actual, variance amount/percentage with color coding by bucket, remove action on drafts. Totals footer. Add-line modal filters out accounts already on the budget. Activate/Archive/Delete buttons gated by policy state.
- [x] Sidebar: "Budgets" entry under Accounting (`ti-target`); breadcrumb `budgets -> 'Budgets'`. **B family is now fully complete on the accounting side** (Bank, Bank Reconciliation, Budgets).

---

## C. AR (Sales lens)

| Item | Status | Owner |
| :--- | :--- | :--- |
| Customers | 🔗 shipped (`/sales/customers`) | sales |
| Quotation | 🔗 shipped (`/sales/quotations`) | sales |
| Invoice | 🔗 shipped (`/sales/invoices`) | sales |
| Receipt Payment | ✅ shipped | accounting |
| Credit Note | ✅ shipped | accounting |
| Debit Note | ✅ shipped | accounting |

### Receipt Payment (Shipped 2026-06-01)
- [x] `receipts` + `receipt_invoice_applications` migrations (000086). Header FKs: `customer_id` → customers (restrict), `bank_account_id` → bank_accounts (restrict), `ar_account_id` → accounts (restrict, asset-typed by service), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Applications FKs: `receipt_id` → receipts (cascade), `invoice_id` → invoices (restrict). Unique `(receipt_number, tenant_id)` + unique `(receipt_id, invoice_id)`. Statuses: `posted` / `cancelled` (no draft — receipts are immediate; mirrors bill_payments).
- [x] `Receipt` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; relations `customer`, `bankAccount`, `arAccount`, `applications`, `invoices` BelongsToMany pivot with `applied_amount`, `journalEntry`, `reversalJournalEntry`. `isCancellable()` helper. Currency auto-uppercased. `ReceiptInvoiceApplication` model with `receipt()` + `invoice()` relations.
- [x] `ReceiptService::record($data)` — AR mirror of BillPaymentService. Validates bank has GL link, AR account is asset type, all invoices belong to header customer, each invoice is `confirmed` with `outstanding > 0`, each applied ≤ outstanding, sum(applied) == header amount (0.001 tolerance). Posts a single DR-bank-GL + one CR-AR per applied invoice via `AccountingService::postEntry`. Per-invoice `paid_amount` rolled and status promoted (`confirmed` → `paid` when fully paid). All atomic. Note: Invoice has no `partially_paid` status — it stays `confirmed` until total reached.
- [x] `ReceiptService::cancel($receipt)` — reverses JE via `AccountingService::reverseEntry`, decrements each invoice's `paid_amount`, downgrades status (`paid` → `confirmed` when no longer fully covered). Preserves audit history.
- [x] `ReceiptPolicy` registered. `update`/`delete` blocked (immutable). `cancel` gated by `write` perm + cancellable state.
- [x] `ReceiptController` + `ReceiptResource` + `ReceiptInvoiceApplicationResource` (camelCase, exposes nested `invoice` snapshot with `outstandingAmount`). Explicit routes: `GET/POST /receipts`, `GET /receipts/{receipt}`, `POST /receipts/{receipt}/cancel`. **No** apiResource. Plus helper `GET /receipts/open-invoices/{customer}` returning confirmed invoices with outstanding > 0 to power the picker without pulling every invoice client-side. Index filters: `?status`, `?customer_id`, `?bank_account_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.receipts.{read,write}` seeded (no delete — immutable).
- [x] Module slugs seeded: introduces new `accounting-receivable` parent slug (sort_order 5, sibling of Disbursement) + `accounting-receivable-customer` cross-link to `/sales/customers` (uses `sales.crm.read/write` perms, hidden when user has direct sales access) + `accounting-receivable-receipts` slug routed to `/accounting/receivable/receipts`.
- [x] UI: `pages/accounting/receivable/receipts/index.vue` — KPI strip (count / received this month / today / invoices settled, all `useCountUp`-animated), status filter chips, paginated table with expandable per-application breakdown showing each linked invoice's status + total + applied + outstanding (post-roll), per-row Cancel with reverse-confirmation modal that explicitly mentions the paid→confirmed downgrade. Record modal: receipt # · received_on · method · ref; customer picker (lazy via `sales.catalogue.listCustomers`); bank picker (lazy, auto-fills currency); AR account picker (lazy from `accounts.tree()` filtered to asset, defaults to code `1200` matching `InvoiceService::DEFAULT_AR_CODE`); auto-loads customer's open invoices via the helper endpoint as a checkbox row defaulting `applied = outstanding`, editable per-row up to outstanding with overflow protection; live "Sum Applied" + "Receipt Amount" balance with "Use sum" one-click amount fill; submit disabled until sum matches header and every checked invoice has positive amount ≤ outstanding.
- [x] Sidebar: new "Receivable" subgroup under Accounting mirroring Disbursement IA. Contains "Customer" cross-link to `/sales/customers` (hidden if user has direct sales access) + "Receipts" entry (`ti-cash`). Breadcrumb map: `receivable → 'Receivable'`, `receipts → 'Receipts'`.

### Credit Note (Shipped 2026-06-01)
- [x] `credit_notes` migration (000087). Single-amount header (no lines). FKs: `customer_id` → customers (restrict), `invoice_id` → invoices (nullable, restrict — unlinked credits allowed for standing customer credit), `sales_returns_account_id` → accounts (restrict; service validates revenue or expense type), `ar_account_id` → accounts (restrict; service validates asset type), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(credit_note_number, tenant_id)`. Statuses: `issued` / `cancelled` (immutable, mirrors disbursement/AR family).
- [x] `CreditNote` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; status constants, relations `customer`, `invoice` (nullable), `salesReturnsAccount`, `arAccount`, `journalEntry`, `reversalJournalEntry`; `isCancellable()` helper. Currency auto-uppercased.
- [x] `CreditNoteService::issue($data)` — validates customer, AR-asset, sales-returns is revenue OR expense (the GAAP-standard contra-revenue or some-tenant discount-expense pattern), amount > 0. When `invoice_id` set: validates invoice belongs to customer + status confirmed + amount ≤ outstanding. Posts `DR Sales Returns / CR AR` via `AccountingService::postEntry`. When linked, rolls invoice.paid_amount forward and promotes confirmed→paid if cleared (mirrors the receipts path). Atomic.
- [x] `CreditNoteService::cancel($note)` — reverses JE via `AccountingService::reverseEntry`, decrements linked invoice's paid_amount, demotes paid→confirmed when no longer fully covered. Preserves audit history.
- [x] `CreditNotePolicy` registered. `update`/`delete` blocked. `create`/`cancel` gated by `fms.credit_notes.write`.
- [x] `CreditNoteController` + `CreditNoteResource` (camelCase, exposes nested `invoice` snapshot with `outstandingAmount` (live)). Explicit routes: `GET/POST /credit-notes`, `GET /credit-notes/{creditNote}`, `POST /credit-notes/{creditNote}/cancel`. **No** apiResource. Index filters: `?status`, `?customer_id`, `?invoice_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.credit_notes.{read,write}` seeded; module slug `accounting-receivable-credit-notes` seeded (sort_order 3 under `accounting-receivable`).
- [x] UI: `pages/accounting/receivable/credit-notes/index.vue` — KPI strip (count / issued this month $ / today / linked-to-invoice ratio, all `useCountUp`), status chips, paginated table with expandable row showing reason + DR/CR account snapshot + linked-invoice badge (with totalAmount/paidAmount/outstandingAmount), per-row Cancel with reverse-confirmation modal (mentions the invoice rollback). Issue modal: credit # · issue_date · amount · customer picker (lazy via `sales.catalogue.listCustomers`); optional invoice picker (auto-loads via `receipts.openInvoicesForCustomer` helper — reused across AR family); sales-returns picker (revenue or expense); AR picker (asset, defaults to 1200); currency; required reason textarea. Live "exceeds outstanding" warning when linked invoice + amount overflow.
- [x] Sidebar: "Credit Notes" entry under Accounting → Receivable subgroup (`ti-file-arrow-left`); breadcrumb `credit-notes → 'Credit Notes'`.

### Debit Note (Shipped 2026-06-01)
- [x] `debit_notes` migration (000088). Single-amount header (no lines). FKs: `customer_id` → customers (restrict), `invoice_id` → invoices (nullable, restrict — traceability only, does NOT modify invoice paid_amount/status), `revenue_account_id` → accounts (restrict; service validates revenue type), `ar_account_id` → accounts (restrict; service validates asset type), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(debit_note_number, tenant_id)`. Statuses: `issued` / `cancelled` (immutable).
- [x] `DebitNote` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; relations `customer`, `invoice` (nullable), `revenueAccount`, `arAccount`, `journalEntry`, `reversalJournalEntry`; `isCancellable()` helper. Currency auto-uppercased.
- [x] `DebitNoteService::issue($data)` — validates customer, AR-asset, revenue type, amount > 0. When `invoice_id` set: validates same customer only (no status / outstanding check — debit notes can attach to ANY invoice including paid/cancelled for traceability). Posts `DR AR / CR Revenue` via `AccountingService::postEntry`. **Deliberately does NOT modify invoice.paid_amount or status** — debit notes stand as their own AR balance, settled by a future Receipt. This is the opposite of CreditNote which folds into the linked invoice. Atomic.
- [x] `DebitNoteService::cancel($note)` — reverses JE via `AccountingService::reverseEntry`. No invoice rollback needed (nothing was applied). Preserves audit history.
- [x] `DebitNotePolicy` registered. `update`/`delete` blocked. `create`/`cancel` gated by `fms.debit_notes.write`.
- [x] `DebitNoteController` + `DebitNoteResource` (camelCase, exposes nested `invoice` snapshot with `outstandingAmount` for context). Explicit routes: `GET/POST /debit-notes`, `GET /debit-notes/{debitNote}`, `POST /debit-notes/{debitNote}/cancel`. **No** apiResource. Index filters: `?status`, `?customer_id`, `?invoice_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.debit_notes.{read,write}` seeded; module slug `accounting-receivable-debit-notes` seeded (sort_order 4 under `accounting-receivable`).
- [x] UI: `pages/accounting/receivable/debit-notes/index.vue` — KPI strip (count / issued this month $ / today / linked-to-invoice ratio, all `useCountUp`), status chips, paginated table with expandable row showing reason + DR/CR account snapshot + linked-invoice badge (explicit "traceability only" note). Issue modal: debit # · issue_date · amount · customer picker (lazy); optional invoice picker (reuses `receipts.openInvoicesForCustomer` helper); AR picker (asset, defaults to code 1200) + revenue picker (revenue, defaults to code 4000) matching `InvoiceService::DEFAULT_AR_CODE`/`DEFAULT_REVENUE_CODE`; currency; required reason textarea.
- [x] Sidebar: "Debit Notes" entry under Accounting → Receivable subgroup (`ti-file-arrow-right`); breadcrumb `debit-notes → 'Debit Notes'`. **AR family is now fully complete on the accounting side** (Receipt + Credit Note + Debit Note).

---

## D. Disbursement / AP

### Vendor (Shipped 2026-06-01)
- [x] `is_vendor` flag + AP fields on `suppliers` (migration 000078): `payment_method`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_swift`, `default_payable_account_id`, `default_expense_account_id`.
- [x] `Supplier` model fillable/cast + `defaultPayableAccount()` / `defaultExpenseAccount()` relations; `SupplierResource` camelCase; `SupplierController` validates new fields + accepts `?vendor_only=1`.
- [x] Frontend: `/inventory/suppliers` Vendor / AP Details modal section + "Vendors only" filter + AP badge per row.

| Item | Status | Owner |
| :--- | :--- | :--- |
| Bill | ✅ shipped | accounting |
| Pay Bill | ✅ shipped | accounting |
| Reimbursement | ✅ shipped | accounting |
| Cash Advance | ✅ shipped | accounting |
| Advance Settlement | ✅ shipped | accounting |
| Expense | ✅ shipped | accounting |

### Bill (Shipped 2026-06-01)
- [x] `bills` + `bill_lines` migrations (000080). FKs: `supplier_id` → suppliers (restrict), `po_id` → purchase_orders (null on delete), `payable_account_id` → accounts (null on delete), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(bill_number, tenant_id)`. Statuses: `draft` / `approved` / `partially_paid` / `paid` / `cancelled`.
- [x] `Bill` model — BelongsToTenant + Auditable + SoftDeletes, status constants + OPEN_STATUSES, `outstandingAmount()` / `isEditable()` / `isPostable()` / `isReversible()` helpers, currency normalized to uppercase on save. `BillLine` recomputes `line_total` in boot.
- [x] `BillService` — create/update (draft-only) replace-lines model, `approve()` posts balanced JE via `AccountingService::postEntry` (DR each line account, CR payable account; tax rolled into AP credit), `cancel()` posts reversal via `AccountingService::reverseEntry` for already-posted bills. All atomic. Vendor must have `is_vendor=true`; AP account falls back to vendor's `default_payable_account_id`.
- [x] `BillPolicy` registered. `update`/`delete` blocked once posted. Approve/cancel gated by `write` perm + state.
- [x] `BillController` + `BillResource` + `BillLineResource` (camelCase). Routes: `apiResource('bills')` + `POST /bills/{bill}/approve` + `POST /bills/{bill}/cancel`. Index filters: `?status`, `?supplier_id`, `?po_id`, `?from`, `?to`, `?open_only`, `?search`.
- [x] Perms `fms.bills.{read,write,delete}` seeded; module slug `accounting-bills` seeded.
- [x] UI: `pages/accounting/disbursement/bills/index.vue` → route `/accounting/disbursement/bills` — KPI strip (count / outstanding AP / due-this-week / overdue, all `useCountUp`-animated), 6 status filter chips wired to `?status=`, paginated table with expandable per-row line + totals breakdown, per-row Edit / Approve / Cancel actions, color-coded due-date column (red overdue, amber within 7d). Create/edit modal with vendor picker (lazy `inventory.suppliers.list({vendor_only:true})`), AP-account picker (filtered to liability/equity), dynamic line builder with computed `line_total` + live subtotal/tax/total footer.
- [x] Sidebar: "Bills" entry under Accounting → **Disbursement** subgroup (`ti-file-invoice`); breadcrumb `bills → 'Bills'`. The Disbursement subgroup also hosts the cross-linked "Vendor" entry pointing at `/inventory/suppliers?vendor_only=1`. ModuleSeeder updated: new `accounting-disbursement` parent slug + `accounting-disbursement-vendor` cross-link slug; existing `accounting-bills` slug re-parented under `accounting-disbursement` (stable slug, only `parent_slug`/`sort_order` changed).
- [ ] **Next**: Pay Bill — `bill_payments` + `bill_payment_applications` consume the outstanding balances and update `paid_amount` + status transitions.

### Pay Bill (Shipped 2026-06-01)
- [x] `bill_payments` + `bill_payment_applications` migrations (000081). FKs: `bank_account_id` → bank_accounts (restrict), `supplier_id` → suppliers (restrict), `bill_payment_id` → bill_payments (cascade), `bill_id` → bills (restrict), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(payment_number, tenant_id)` + unique `(bill_payment_id, bill_id)`. Statuses: `posted` / `cancelled` (no draft — payments are immediate).
- [x] `BillPayment` model with BelongsToTenant + Auditable + SoftDeletes; `bankAccount()`, `supplier()`, `applications()`, `bills()` BelongsToMany pivot, `journalEntry()` + `reversalJournalEntry()`, `isCancellable()` helper. `BillPaymentApplication` model with `payment()` + `bill()`.
- [x] `BillPaymentService::record($data)` — validates bank has GL link, all bills belong to header supplier, all bills are open, each applied ≤ outstanding, sum(applied) == header amount (0.001 tolerance). Posts DR-AP-per-bill + single CR-to-bank-GL journal via `AccountingService::postEntry`. Per-bill `paid_amount` rolled and status promoted (`approved` → `partially_paid` → `paid`). All atomic.
- [x] `BillPaymentService::cancel($payment)` — reverses JE via `AccountingService::reverseEntry`, decrements each bill's `paid_amount`, downgrades status (`paid` → `partially_paid` → `approved`). Preserves audit history.
- [x] `BillPaymentPolicy` registered. `update`/`delete` blocked (immutable). `cancel` gated by `write` perm + cancellable state.
- [x] `BillPaymentController` + `BillPaymentResource` + `BillPaymentApplicationResource` (camelCase). Routes: `GET/POST /bill-payments`, `GET /bill-payments/{billPayment}`, `POST /bill-payments/{billPayment}/cancel`. **No** apiResource — PUT/PATCH/DELETE intentionally omitted to mirror policy. Index filters: `?status`, `?supplier_id`, `?bank_account_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.bill_payments.{read,write}` seeded (no delete — immutable); module slug `accounting-disbursement-pay-bills` seeded.
- [x] UI: `pages/accounting/disbursement/pay-bills/index.vue` — KPI strip (count / paid this month / today / bills settled, all `useCountUp`-animated), status filter chips (All / Posted / Cancelled), paginated table with expandable per-application breakdown showing each bill's status + applied vs outstanding, per-row Cancel action with reverse-confirmation modal. Record modal: payment # · date · method · ref; vendor + bank pickers (lazy-loaded); auto-loads vendor's open bills (`?supplier_id=...&open_only=true`); each open bill is a checkbox row defaulting `applied = outstanding` when checked, editable; live "Sum Applied" + balance badge with "Use sum" one-click amount fill; submit disabled until sum matches header amount and every checked bill has positive amount ≤ outstanding.
- [x] Sidebar: "Pay Bill" entry under Accounting → Disbursement subgroup (`ti-cash-register`); breadcrumb `pay-bills → 'Pay Bill'`.

### Reimbursement (Shipped 2026-06-01)
- [x] `reimbursements` + `reimbursement_lines` migrations (000082). FKs: `employee_id` → employees (restrict), `bank_account_id` → bank_accounts (restrict), `reimbursement_id` → reimbursements (cascade), `account_id` → accounts (restrict), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(reimbursement_number, tenant_id)`. Statuses: `posted` / `cancelled` (immediate — no draft, mirrors bill_payments).
- [x] `Reimbursement` model with BelongsToTenant + Auditable + SoftDeletes; `employee()`, `bankAccount()`, `lines()`, `journalEntry()`, `reversalJournalEntry()`, `isCancellable()`. `ReimbursementLine` with `account()` relation.
- [x] `ReimbursementService::record($data)` — validates bank has GL link, each line account is expense-type, every line amount > 0, sum(lines) == header amount (0.001 tolerance). Posts DR-per-line + single CR-to-bank-GL via `AccountingService::postEntry`. No AP step (skips bills entirely — employees aren't vendors). Atomic.
- [x] `ReimbursementService::cancel($reimb)` — reverses JE via `AccountingService::reverseEntry`, stores reversal id, flips status. Audit history preserved.
- [x] `ReimbursementPolicy` registered. `update`/`delete` blocked (immutable). `cancel` gated by `write` + cancellable state.
- [x] `ReimbursementController` + `ReimbursementResource` + `ReimbursementLineResource` (camelCase). Explicit routes: `GET/POST /reimbursements`, `GET /reimbursements/{reimbursement}`, `POST /reimbursements/{reimbursement}/cancel`. No apiResource (PUT/PATCH/DELETE never reach a handler). Index filters: `?status`, `?employee_id`, `?bank_account_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.reimbursements.{read,write}` seeded (no delete — immutable); module slug `accounting-disbursement-reimbursements` seeded.
- [x] UI: `pages/accounting/disbursement/reimbursements/index.vue` — KPI strip (count / paid this month / today / total lines, all `useCountUp`), status filter chips (All / Posted / Cancelled), paginated table with expandable per-line breakdown, per-row Cancel action with reverse-confirmation modal. Create modal: reimb # · date · method · ref; employee picker (lazy `/employees?limit=200`); bank picker (lazy `bankAccounts.list({is_active:true})`); dynamic line builder with expense-account picker (filtered from `accounts.tree()`), description, amount; live sum-of-lines + balance badge with "Use sum" button; submit disabled until sum matches header and every line valid.
- [x] Sidebar: "Reimbursement" entry under Accounting → Disbursement subgroup (`ti-receipt-2`); breadcrumb `reimbursements → 'Reimbursements'`.
- [ ] **Future**: per-line receipt attachment upload (column already in schema; nullable). Will pair with the existing tenant uploader.

### Cash Advance (Shipped 2026-06-01)
- [x] `cash_advances` migration (000083). FKs: `employee_id` → employees (restrict), `bank_account_id` → bank_accounts (restrict), `receivable_account_id` → accounts (restrict; the Employee Advances Receivable asset), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(advance_number, tenant_id)`. Statuses: `open` / `partially_settled` / `closed` / `cancelled`. `settled_amount` running total updated by future Settlement entries.
- [x] `CashAdvance` model with BelongsToTenant + Auditable + SoftDeletes; status constants + OPEN_STATUSES (`open`, `partially_settled`); relations (employee, bankAccount, receivableAccount, journalEntry, reversalJournalEntry); `outstandingAmount()`, `isCancellable()` helpers. Currency auto-uppercased.
- [x] `CashAdvanceService::issue($data)` — validates bank has GL link, receivable account is asset type, employee exists. Posts DR Receivable / CR Cash via `AccountingService::postEntry`. Status=open. Atomic.
- [x] `CashAdvanceService::cancel($advance)` — refuses if `settled_amount > 0` (settlements must be reversed first). Reverses JE via `AccountingService::reverseEntry`, flips status=cancelled.
- [x] `CashAdvancePolicy` registered. `update`/`delete` blocked (immutable). `cancel` gated by `write` + `isCancellable`.
- [x] `CashAdvanceController` + `CashAdvanceResource` (camelCase, exposes `outstandingAmount`). Explicit routes: `GET/POST /cash-advances`, `GET /cash-advances/{cashAdvance}`, `POST /cash-advances/{cashAdvance}/cancel`. Index filters: `?status`, `?employee_id`, `?open_only`, `?from`, `?to`, `?search`.
- [x] Perms `fms.cash_advances.{read,write}` seeded (no delete — immutable; `settle` perm added with Settlement entity); module slug `accounting-disbursement-cash-advances` seeded.
- [x] UI: `pages/accounting/disbursement/cash-advances/index.vue` — KPI strip (outstanding $, issued this month, closed count, total count, all `useCountUp`), 5 status filter chips, table with amount / settled / outstanding columns + status badge + per-row Cancel (only when `isCancellable`). Issue modal: advance # · issued date · amount · employee picker (lazy `/employees?limit=200`) · bank picker (lazy `bankAccounts.list`, auto-fills currency) · **asset-account-only** receivable picker · purpose · payment method · reference.
- [x] Sidebar: "Cash Advance" entry under Accounting → Disbursement (`ti-wallet`); breadcrumb `'cash-advances' → 'Cash Advances'`.

### Advance Settlement (Shipped 2026-06-01)
- [x] `cash_advance_settlements` + `cash_advance_settlement_lines` migrations (000084). FKs: `cash_advance_id` → cash_advances (restrict), `bank_account_id` → bank_accounts (restrict, nullable — required only when `unused_returned > 0`), `settlement_id` → cash_advance_settlements (cascade), `account_id` → accounts (restrict, expense-typed by service), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Unique `(settlement_number, tenant_id)`. Statuses: `posted` / `cancelled` (immutable — mirrors bill_payments/reimbursements/cash_advances).
- [x] `CashAdvanceSettlement` + `CashAdvanceSettlementLine` models with `BelongsToTenant` + `Auditable` + `SoftDeletes` (header). Header: status constants, relations (`cashAdvance`, `bankAccount`, `lines`, `journalEntry`, `reversalJournalEntry`), `appliedToAdvance()` = actual − unused_returned, `isCancellable()` helper. Line: `settlement()` + `account()` relations.
- [x] `CashAdvanceSettlementService::record($data)` — validates advance is in `OPEN_STATUSES`, each line account is expense type, sum(lines) == actual_amount (0.001 tolerance), `applied = actual − unused_returned > 0` and `≤ advance.outstandingAmount`, bank GL link required when `unused_returned > 0`. Posts via `AccountingService::postEntry`: DR each expense line + DR cash (if unused returned) + CR advance.receivableAccount (= actual). Rolls `advance.settled_amount += applied` and promotes status (`open → partially_settled → closed`) under row lock. Atomic.
- [x] `CashAdvanceSettlementService::cancel($settlement)` — reverses JE via `AccountingService::reverseEntry`, decrements `advance.settled_amount` by `appliedToAdvance`, downgrades advance status (`closed → partially_settled → open` as appropriate) under row lock. Preserves audit history.
- [x] `CashAdvanceSettlementPolicy` registered. `update`/`delete` blocked (immutable). `create` gated by `fms.cash_advances.settle`. `cancel` gated by `settle` perm + cancellable state.
- [x] `CashAdvanceSettlementController` + `CashAdvanceSettlementResource` + `CashAdvanceSettlementLineResource` (camelCase, exposes `appliedToAdvance` + nested `cashAdvance` snapshot with `outstandingAmount`). Explicit routes: `GET/POST /cash-advance-settlements`, `GET /cash-advance-settlements/{cashAdvanceSettlement}`, `POST /cash-advance-settlements/{cashAdvanceSettlement}/cancel`. **No** apiResource. Index filters: `?status`, `?cash_advance_id`, `?employee_id` (joined via advance), `?bank_account_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.cash_advances.settle` seeded; module slug `accounting-disbursement-advance-settlements` seeded under `accounting-disbursement` (sort_order 6).
- [x] UI: `pages/accounting/disbursement/advance-settlements/index.vue` — KPI strip (count / settled this month $ / today / unused returned $, all `useCountUp`-animated), status filter chips (All / Posted / Cancelled), paginated table with expandable per-line breakdown showing each expense line + unused-returned row + actual/applied summary, per-row Cancel with reverse-confirmation modal (shows applied amount that will roll back). Record modal: settlement # · settled_on · method · ref; **open cash-advance picker** (lazy `cashAdvances.list({open_only:true})`, shows outstanding); expense-account-per-line builder (lazy from `accounts.tree()` filtered to expense); unused_returned + bank picker (required when > 0); live "Sum of Lines" + "Lines balanced" + "Applied to Advance" + "Outstanding" footer; submit disabled until lines balance, applied fits within outstanding, and bank is set when needed. Auto-defaults `actual_amount` to the selected advance's outstanding for the common "spent it all" case.
- [x] Sidebar: "Advance Settlement" entry under Accounting → Disbursement subgroup (`ti-receipt-refund`); breadcrumb `'advance-settlements' → 'Advance Settlements'`.

### Expense (Shipped 2026-06-01)
- [x] `expenses` + `expense_lines` migrations (000085). Header FKs: `bank_account_id` → bank_accounts (restrict), `supplier_id` → suppliers (restrict, nullable — petty-cash traceability only, no AP cycle), `journal_entry_id` + `reversal_journal_entry_id` → journal_entries (null on delete). Lines FKs: `expense_id` → expenses (cascade), `account_id` → accounts (restrict; expense-typed by service). Unique `(expense_number, tenant_id)`. Statuses: `posted` / `cancelled` (immutable — matches the disbursement family shape).
- [x] `Expense` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`, status constants, relations (`bankAccount`, `supplier`, `lines`, `journalEntry`, `reversalJournalEntry`), `isCancellable()`. Currency auto-uppercased. `ExpenseLine` with `expense()` + `account()` relations.
- [x] `ExpenseService::record($data)` — validates bank has GL link, each line account is expense type, sum(lines) == total (0.001 tolerance), each line positive. Posts DR each expense line + single CR bank-GL via `AccountingService::postEntry`. Atomic. Supplier (if provided) used for traceability only.
- [x] `ExpenseService::cancel($expense)` — reverses JE via `AccountingService::reverseEntry`, stores reversal id, flips status. Audit history preserved.
- [x] `ExpensePolicy` registered. `update`/`delete` blocked (immutable). `cancel` gated by `write` perm + cancellable state.
- [x] `ExpenseController` + `ExpenseResource` + `ExpenseLineResource` (camelCase). Explicit routes: `GET/POST /expenses`, `GET /expenses/{expense}`, `POST /expenses/{expense}/cancel`. **No** apiResource — mirrors policy. Index filters: `?status`, `?supplier_id`, `?bank_account_id`, `?from`, `?to`, `?search`.
- [x] Perms `fms.expenses.{read,write}` seeded (no `delete` — immutable); module slug `accounting-disbursement-expenses` seeded under `accounting-disbursement` (sort_order 7).
- [x] UI: `pages/accounting/disbursement/expenses/index.vue` — KPI strip (count / spent this month / today / lines, all `useCountUp`-animated), status filter chips, paginated table with expandable per-line breakdown showing each expense account + total, per-row Cancel with reverse-confirmation modal. Record modal: expense # · paid_on · method · ref; bank picker (lazy `bankAccounts.list`, auto-fills currency on change); optional supplier picker (lazy `inventory.suppliers.list`); dynamic line builder with expense-account picker (lazy from `accounts.tree()` filtered to expense); live "Sum of Lines" + "Balanced" badge with "Use sum" button; submit disabled until sum matches total and every line valid.
- [x] Sidebar: "Expense" entry under Accounting → Disbursement subgroup (`ti-receipt-tax`); breadcrumb `expenses → 'Expenses'`.

---

## E. Inventory (lens)

| Item | Status | Owner | Path |
| :--- | :--- | :--- | :--- |
| Create new item | 🔗 shipped | inventory | `/inventory/products` |
| Purchase Order | 🔗 shipped (full FSM) | inventory | `/inventory/purchase-orders` |
| Cost of Purchase (WAC) | 🔗 shipped (inline on PO receipt) | inventory | `/inventory/products` (cost column) |
| Inventory Adjustment | 🔗 shipped (stock movements ledger) | inventory | `/inventory/products` (movements panel) |
| Request Order (PR) | ⬜ planned | inventory | `/inventory/purchase-requests` (TBD) |

**Accounting-side work**: ensure each operational event posts the right GL impact through `AccountingService::postEntry`. Currently WAC writes the inventory `cost` field but does not post a parallel `DR Inventory / CR GR-IR` journal — this gap should be tracked in `.task/inventory/task.md` once Bills are introduced.

---

## F. Employees (lens)

| Item | Status | Owner | Path |
| :--- | :--- | :--- | :--- |
| Personal Administration | 🔗 shipped | hrm | `/hrm/employees` |
| Payroll Accounting | 🔗 shipped (run + payslip generation) | hrm | `/hrm/payroll/periods` |

**Accounting-side gap**: payroll period close should post `DR Salary Expense, CR Salaries Payable / Tax Withheld / Cash` via `AccountingService::postEntry`. Track under `.task/hrm/task.md` once available.

---

## G. Non-Current Asset (lens)

| Item | Status | Owner | Path |
| :--- | :--- | :--- | :--- |
| Purchase Order (capitalizable) | 🔗 shipped (general PO) | inventory | `/inventory/purchase-orders` |
| Register new Asset | 🔗 planned | assets | `/assets` |
| Depreciation & Amortization | 🔗 planned | assets | `/assets/depreciation` |
| Asset Disposal | 🔗 planned | assets | `/assets/disposal` |

**Accounting-side work**: tracked in [`.task/assets/task.md`](../assets/task.md). Each lifecycle event must call `AccountingService::postEntry` with the right account fallbacks (`assets.depreciation.expense_account`, `assets.depreciation.accumulated_account` settings).

---

## Open Cross-Cutting Items

- [ ] **Sidebar IA pass** — restructure top-level `Accounting` sidebar group to mirror the eight families above. Today it has only Chart of Accounts + Journals. Some leaves will be `route`-linked to existing pages with an "Accounting" breadcrumb override.
- [x] **Module slug seeding** complete for all currently shipped accounting features: `accounting-bank`, `accounting-bank-reconciliation`, `accounting-budgets`, `accounting-fiscal-periods`, `accounting-bills`, `accounting-disbursement-pay-bills`, `accounting-disbursement-reimbursements`, `accounting-disbursement-cash-advances`, `accounting-disbursement-advance-settlements`, `accounting-disbursement-expenses`, `accounting-receivable`, `accounting-receivable-customer`, `accounting-receivable-receipts`, `accounting-receivable-credit-notes`, `accounting-receivable-debit-notes`.
- [ ] **Permission seeding** — add the slugs listed in [`rules.md`](../../skills/accounting/rules.md) § B-D to `TenantDatabaseSeeder` as each feature is shipped.
- [ ] **`accounting.base_currency` tenant setting** — establish before Phase 3 FX work.
