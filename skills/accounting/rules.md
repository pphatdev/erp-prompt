# Accounting & General Ledger Workflow Rules

## 1. Permissions (IAM Integration)

Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
* **Module**: `fms` (Financial Management System)
* **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
| :--- | :--- | :--- | :--- | :--- |
| `accounts` (Chart of Accounts) | `fms.accounts.read` | `fms.accounts.write` | `fms.accounts.delete` | `fms.accounts.export` |
| `ledger` (Journal Posting) | `fms.ledger.read` | `fms.ledger.write` | — | `fms.ledger.export` |
| `exchange-rates` (Currencies) | `fms.rates.read` | `fms.rates.write` | `fms.rates.delete` | `fms.rates.export` |
| `closing` (Fiscal Periods) | `fms.closing.read` | `fms.closing.write` | — | — |

> **Important**: The `delete` action is explicitly omitted for `ledger` postings to enforce immutability. General ledger entries cannot be removed once posted; adjusting entries (reversals) must be written instead.

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
