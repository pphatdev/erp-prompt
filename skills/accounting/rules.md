# Accounting & General Ledger Workflow Rules

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
* **Module**: `fms` (Financial Management System)
* **Actions**: `read`, `write`, `delete`, `export`

### A. Core Features (owned by Accounting / FMS namespace)

| Feature | Read | Write | Delete | Export |
| :--- | :--- | :--- | :--- | :--- |
| `accounts` (Chart of Accounts) | `fms.accounts.read` | `fms.accounts.write` | `fms.accounts.delete` | `fms.accounts.export` |
| `ledger` (Journal Posting) | `fms.ledger.read` | `fms.ledger.write` | — | `fms.ledger.export` |
| `exchange_rate` (Currencies) | `fms.exchange_rate.read` | `fms.exchange_rate.write` | `fms.exchange_rate.delete` | — |
| `closing` (Fiscal Periods) | `fms.closing.read` | `fms.closing.write` | — | — |

> **Important**: The `delete` action is explicitly omitted for `ledger` postings to enforce immutability. General ledger entries cannot be removed once posted; adjusting entries (reversals) must be written via `AccountingService::reverseEntry()` instead.

### B. AR (Sales lens) Features

| Feature | Read | Write | Delete | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `receipts` (Receipt Payments) | `fms.receipts.read` | `fms.receipts.write` | `fms.receipts.delete` | DR Cash, CR AR. Partial-apply against one or many invoices. |
| `credit_notes` (Customer credit) | `fms.credit_notes.read` | `fms.credit_notes.write` | `fms.credit_notes.delete` | DR Sales Returns, CR AR. Reduces customer balance. |
| `debit_notes` (Customer chargeback) | `fms.debit_notes.read` | `fms.debit_notes.write` | `fms.debit_notes.delete` | DR AR, CR Revenue. Increases customer balance. |

Cross-linked AR pages (Customers / Quotation / Invoice) reuse Sales perms `sales.crm.*` and `sales.orders.*` — no Accounting-side perm is added.

### C. AP (Disbursement) Features

| Feature | Read | Write | Delete | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `bills` (Supplier invoices in AP) | `fms.bills.read` | `fms.bills.write` | `fms.bills.delete` | DR Expense/Asset, CR AP. Distinct from PO. |
| `bill_payments` (Pay Bill) | `fms.bill_payments.read` | `fms.bill_payments.write` | `fms.bill_payments.delete` | DR AP, CR Cash. Settles one or many bills. |
| `reimbursements` (Employee out-of-pocket) | `fms.reimbursements.read` | `fms.reimbursements.write` | `fms.reimbursements.delete` | DR Expense, CR Cash. |
| `cash_advances` (Employee advance) | `fms.cash_advances.read` | `fms.cash_advances.write` | `fms.cash_advances.delete` | DR Employee Advances, CR Cash. |
| `cash_advances.settle` (Advance Settlement) | — | `fms.cash_advances.settle` | — | DR Expense + (CR/DR) Employee Advances. Closes a Cash Advance. |
| `expenses` (Direct expense entry) | `fms.expenses.read` | `fms.expenses.write` | `fms.expenses.delete` | DR Expense, CR Cash/Credit Card. No Bill stage. |

Cross-linked vendor master uses `inventory.suppliers.*` (already shipped via the Supplier AP extension, see migration 000078).

### D. Bank & Budget Features

| Feature | Read | Write | Delete | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `bank_accounts` | `fms.bank_accounts.read` | `fms.bank_accounts.write` | `fms.bank_accounts.delete` | Specialized view of asset-type accounts with bank metadata + reconciliation. |
| `bank_recon` | `fms.bank_recon.read` | `fms.bank_recon.write` | — | Bank statement matching session — immutable once closed. |
| `budgets` | `fms.budgets.read` | `fms.budgets.write` | `fms.budgets.delete` | Period-bound expected DR/CR per account. |

### E. Cross-Module Lens (No new perms — reuse owner-module perms)

| Accounting Menu | Owner Permission Family |
| :--- | :--- |
| Sales > Customers / Quotation / Invoice | `sales.crm.*`, `sales.orders.*` |
| Inventory > Items / PO / Adjustment | `inventory.product.*`, `inventory.procurement.*`, `inventory.stock.*` |
| Inventory > Request Order (PR — planned) | `inventory.procurement.request` (to be seeded with the PR feature) |
| Employees > Personal Administration | `hrm.employee.*` |
| Employees > Payroll Accounting | `hrm.payroll.*` |
| Non-Current Asset > Register / Depreciation / Disposal | `assets.assets.*`, `assets.depreciation.*`, `assets.disposal.*` |

---

## 2. Implementation Standards

### A. General Ledger Integrity
1. **Mathematical Balance**: Every journal entry MUST balance precisely:
   $$\sum \text{Debits} = \sum \text{Credits}$$
   If the difference is greater than `0.001`, the system must fail validation and throw a domain-specific exception.
2. **Transaction Atomicity**: Writing a journal entry and its lines, alongside updating account balances, MUST be wrapped within a database transaction block:
   ```php
   DB::transaction(function () use ($data) {
       // Validate -> Create Journal Header -> Create Ledger Lines -> Adjust Account Balances
   });
   ```
3. **Account Balance Drift Prevention**: Do not aggregate total balances dynamically from the `ledger_entries` table for basic read queries. Query the pre-aggregated `balance` field in `accounts` table directly, which is updated atomically on posting.

### B. Chart of Accounts Constraints
1. **Scope and Uniqueness**: The `code` column on the `Account` model must be unique within the same tenant. Implement database/validation rule:
   ```php
   Rule::unique('accounts')->where('tenant_id', tenant('id'))->ignore($accountId)
   ```
2. **Hierarchy Loops**: When saving an `Account` with a `parent_id`, validate that the parent is not a child of the current account (circular hierarchy check).

### C. Multi-Currency Normalization
1. **Case Standardization**: All currency codes (e.g., `base_currency`, `quote_currency`) must be converted to uppercase during creation/updating:
   ```php
   static::creating(function ($model) {
       $model->base_currency = strtoupper($model->base_currency);
       $model->quote_currency = strtoupper($model->quote_currency);
   });
   ```
2. **High-Precision Decimal**: Always cast the exchange `rate` as a six-decimal value:
   ```php
   protected $casts = [
       'rate' => 'decimal:6',
       'effective_date' => 'date',
   ];
   ```

---

## 3. Frontend (Nuxt/Vue 3) Standards

### A. Navigation & Layout
- **Path structure**: Files must exist in `frontend/pages/accounting/` to maintain modular separation.
- **Tree Visualization**: Use high-quality visual trees (e.g., PrimeVue Tree or custom component) to display the hierarchical Chart of Accounts.

### B. Form Validation
- **Balanced Check**: The journal entry posting interface must prevent form submission if the total debits do not equal total credits.
- **Line Validation**: Prevent adding ledger lines with empty accounts, or lines containing both non-zero debits and credits simultaneously (a single line is either a debit OR a credit).
- **Number Inputs**: Use strict numeric controls with a minimum step of `0.01` to match standard currency increments.
