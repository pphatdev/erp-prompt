---
name: accounting
description: Double-entry general ledger, hierarchical Chart of Accounts (COA), multi-tenant transaction posting, exchange rate conversions, and period-closing compliance.
---
# Accounting & General Ledger (Accounting)

Use this skill when implementing accounting models, general ledger entries, multi-currency conversions, or financial reporting workflows. Ensuring high mathematical integrity, strict double-entry balance, and complete tenant isolation are the primary directives of this module.

## Module Surface & Routing

The Accounting features are exposed via the following logical navigation pathways:

```
Accounting
├── Chart of Accounts — /accounting/accounts, /api/v1/fms/accounts
├── Journal Entries   — /accounting/journals, /api/v1/fms/ledger (list and post)
└── Exchange Rates    — /accounting/exchange-rates, /api/v1/fms/exchange-rates
```

* **Note**: Controllers and endpoints are served by the `FMS` backend module under `App\Tenants\Modules\FMS\*` but represent the core accounting system. All routes are prefixed with `/api/v1/` and scoped to the active tenant using the `InitializeTenancyByHandle` middleware.

---

## Technical Domain & Schema Mapping

The core accounting system relies on four Eloquent models under the `App\Models\Tenant` namespace. Any service or controller interacting with general ledger records must strictly conform to these structures:

### 1. Chart of Accounts (`Account.php`)
Represents individual accounts in the ledger.
* **Namespace**: `App\Models\Tenant\Account`
* **Primary Key**: UUID string (`incrementing = false`)
* **Properties**:
  * `code` (string, e.g., `"1010"`, `"5400"`) — Unique identifier per tenant.
  * `name` (string) — Descriptive name (e.g., `"Cash at Bank"`, `"Depreciation Expense"`).
  * `type` (string) — Classification, typically: `asset`, `liability`, `equity`, `revenue`, `expense`.
  * `parent_id` (string|null, self-referencing foreign key) — For hierarchical grouping of accounts.
  * `balance` (decimal) — Running total of ledger postings.
  * `tenant_id` (string) — Tenant isolation key.

### 2. Journal Entry Header (`JournalEntry.php`)
Represents the transactional envelope (header) for double-entry records.
* **Namespace**: `App\Models\Tenant\JournalEntry`
* **Primary Key**: UUID string (`incrementing = false`)
* **Properties**:
  * `reference_number` (string) — Standardized transaction reference (e.g., `"DEPR-AST-001"`, `"INV-10029"`).
  * `description` (string|null) — Transaction memo or context.
  * `entry_date` (date/datetime) — Date of financial record posting.
  * `status` (string) — Lifecycle state: `draft` or `posted`.
  * `tenant_id` (string) — Tenant isolation key.

### 3. Ledger Entry Line (`LedgerEntry.php`)
Represents individual debit/credit transaction lines linked to a header.
* **Namespace**: `App\Models\Tenant\LedgerEntry`
* **Primary Key**: UUID string (`incrementing = false`)
* **Properties**:
  * `journal_entry_id` (string, foreign key to `JournalEntry`)
  * `account_id` (string, foreign key to `Account`)
  * `debit` (decimal) — Debit amount (must be positive or zero).
  * `credit` (decimal) — Credit amount (must be positive or zero).
  * `tenant_id` (string) — Tenant isolation key.

### 4. Exchange Rates (`ExchangeRate.php`)
Represents exchange rates for converting multi-currency transactions.
* **Namespace**: `App\Models\Tenant\ExchangeRate`
* **Primary Key**: UUID string (`incrementing = false`)
* **Properties**:
  * `base_currency` (string, stored as uppercase, e.g., `"USD"`)
  * `quote_currency` (string, stored as uppercase, e.g., `"KHR"`)
  * `rate` (decimal:6, six decimal precision) — Conversion multiplier.
  * `effective_date` (date) — Start date of rate validity.
  * `source` (string|null) — Reference source (e.g., `"NBC"`, `"Central Bank"`).
  * `notes` (string|null) — Contextual notes.
  * `is_active` (boolean) — Status flag.
  * `tenant_id` (string) — Tenant isolation key.

---

## Core Workflows

### 1. Chart of Accounts Management
- **Creation**: Accounts are created with a unique `code` and classified under an account `type`.
- **Hierarchy Validation**: When assigning a `parent_id`, code must ensure there are no circular references (e.g., an account cannot be its own parent or descendant).
- **Balance Aggregation**: Querying balances must optionally support tree summation, aggregating child balances into parent headers.

### 2. General Ledger Posting
All transactions are captured via the `AccountingService::postEntry(array $data)` boundary.
- **Payload Structure**:
  ```php
  $data = [
      'reference_number' => 'JV-2026-0001',
      'description' => 'Monthly Rent Expense',
      'entry_date' => '2026-05-30',
      'lines' => [
          ['account_id' => 'uuid-rent-expense', 'debit' => 1200.00, 'credit' => 0.00],
          ['account_id' => 'uuid-cash-at-bank', 'debit' => 0.00, 'credit' => 1200.00]
      ]
  ];
  ```
- **Execution Lifecycle**:
  1. Validate that the lines balance perfectly (`Sum(Debits) === Sum(Credits)`).
  2. Start DB transaction (`DB::transaction`).
  3. Create `JournalEntry` header in `status='posted'`.
  4. Create `LedgerEntry` rows linked to the journal.
  5. Update the running `balance` column of each impacted `Account` (e.g., `balance += (debit - credit)`).
  6. Commit transaction.

### 3. Multi-Currency Postings
- **Retrieval**: Resolve active `ExchangeRate` for the transaction date where `base_currency` and `quote_currency` match target codes.
- **Validation**: Enforce uppercase formatting on all currencies to prevent string lookup mismatches.
- **Conversion**: Apply conversion formula (`Base Amount * Rate = Quote Amount`) and write corresponding local/functional currency entries into the general ledger.

---

## Best Practices & Safety

- **Atomic Postings**: Never modify or insert ledger entries outside of a DB transaction block. If one line fails, the entire journal entry must rollback.
- **Immutable Ledger**: Never allow deleting or updating a `posted` journal or ledger entry. To correct a mistake, post an offsetting reversal journal entry.
- **Tenant Scope Enforcement**: Ensure `tenant_id` is automatically injected into all query scopes and write statements by leveraging standard traits:
  * `BelongsToTenant`
  * `Auditable`
- **Decimal Precision**: Always perform arithmetic operations on currency fields using high-precision decimals (`DECIMAL(19,4)` on database level or BCMath/Decimal casting in PHP) to avoid floating-point rounding errors.

---

## Troubleshooting & Common Mistakes

- **Unbalanced Ledger Exceptions**: Occur when `abs(debits - credits) > 0.001`. Always inspect the sum calculation of lines in custom modules or integration services before dispatching to `AccountingService`.
- **Missing Account Seeds**: If custom modules fail to resolve account codes (e.g., looking up code `1500` for accumulated depreciation), ensure that:
  1. The Chart of Accounts contains the target code.
  2. The code matches the tenant setting or the module's fallback.
- **Floating Point Mismatches**: Never use native PHP float summing (`+=`) for currency. Use array collection sum mechanisms or BCMath inside services for precision checks.
